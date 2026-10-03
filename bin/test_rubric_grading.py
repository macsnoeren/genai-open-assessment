# Copyright (C) 2025 JMNL Innovation.
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.

"""
Mocktests voor de rubric-beoordeling in process_ai_feedback.py.
call_ollama() wordt gepatcht, er gaat dus niets naar Ollama of de webapp.

fixtures/criteria_rubric.txt is de uitvoer van QuestionDesign::rubricToCriteriaText()
in PHP voor de rubric uit fixtures/validation.json. Verandert dat formaat, maak
de fixture dan opnieuw aan (zie bin/README.md).

Draaien (vereist een bin/config.py):
    cd bin && python3 -m unittest test_rubric_grading -v
"""

import os
import re
import unittest
from unittest import mock

import process_ai_feedback as paf

FIXTURES = os.path.join(os.path.dirname(os.path.abspath(__file__)), "fixtures")

# Dezelfde regex als in DocentController en StudentExamController (contract 1).
PHP_SCORE_REGEX = re.compile(r'Model:\s+(.+?)\s+.*?Aantal punten:\s+(\d+)', re.IGNORECASE | re.DOTALL)


def criteria_text():
    with open(os.path.join(FIXTURES, "criteria_rubric.txt"), encoding="utf-8") as f:
        return f.read()


def answer(**fields):
    base = {
        "student_answer_id": 42,
        "answer": "Een PLC heeft vaak geen wachtwoord, dus een hacker kan de machine stilzetten.",
        "question_text": "Leg uit waarom een PLC niet rechtstreeks met internet verbonden zou moeten zijn.",
        "criteria": criteria_text(),
        "prompt_text": None,
    }
    base.update(fields)
    return base


def model_output(score=5, statuses=("voldaan", "deels", "niet")):
    return {
        "criteria": [{"nr": i, "status": s, "toelichting": f"Toelichting {i}."} for i, s in enumerate(statuses, 1)],
        "score": score,
        "feedback": "Je noemt de zwakke beveiliging.",
        "uitleg": "Leg ook de gevolgen uit.",
    }


class ParseRubricTest(unittest.TestCase):

    def test_parses_php_format(self):
        rubric = paf.parse_rubric_criteria(criteria_text())
        self.assertIsNotNone(rubric)
        self.assertEqual([c["weight"] for c in rubric["criteria"]], ["essentieel", "essentieel", "aanvullend"])
        self.assertEqual(rubric["criteria"][0]["name"], "Zwakke beveiliging van de PLC")
        self.assertTrue(rubric["criteria"][0]["description"].startswith("De student legt uit"))
        self.assertEqual(sorted(rubric["levels"]), [0, 1, 5, 10])
        self.assertTrue(rubric["levels"][10].startswith("Volledig correct:"))
        self.assertEqual(len(rubric["alternatives"]), 2)
        self.assertTrue(rubric["model_answer"].startswith("PLC's zijn slecht beveiligd"))

    def test_crlf_from_textarea(self):
        # Een browser stuurt textarea-inhoud met \r\n.
        rubric = paf.parse_rubric_criteria(criteria_text().replace("\n", "\r\n"))
        self.assertIsNotNone(rubric)
        self.assertEqual(len(rubric["criteria"]), 3)

    def test_continuation_line_is_kept(self):
        text = criteria_text().replace(
            "met gevolgen voor veiligheid, productie of milieu.",
            "met gevolgen voor veiligheid,\nproductie of milieu.",
        )
        rubric = paf.parse_rubric_criteria(text)
        self.assertTrue(rubric["criteria"][1]["description"].endswith("veiligheid, productie of milieu."))

    def test_without_alternatives_and_model_answer(self):
        text = criteria_text().split("\nOok correct:")[0]
        text = text.split("Beoordelingscriteria:", 1)[1]
        rubric = paf.parse_rubric_criteria("Beoordelingscriteria:" + text)
        self.assertEqual(rubric["alternatives"], [])
        self.assertEqual(rubric["model_answer"], "")

    def test_plain_criteria_are_not_a_rubric(self):
        self.assertIsNone(paf.parse_rubric_criteria("10 punten als het antwoord firewall noemt."))
        self.assertIsNone(paf.parse_rubric_criteria(""))
        self.assertIsNone(paf.parse_rubric_criteria(None))

    def test_broken_structure_falls_back(self):
        text = criteria_text()
        cases = {
            "niveau ontbreekt": text.replace("1 punt: ", "Eén punt: "),
            "onbekend gewicht": text.replace("[aanvullend]", "[optioneel]"),
            "tekst voor het eerste kopje": "Let op!\n" + text,
            "dubbel kopje": text + "\nPuntentoekenning:\n10 punten: x",
            "niveau dubbel": text.replace("0 punten:", "5 punten:"),
        }
        for label, broken in cases.items():
            with self.subTest(label):
                self.assertIsNone(paf.parse_rubric_criteria(broken))


class RubricPromptTest(unittest.TestCase):

    def test_prompt_contains_rubric_and_not_answer(self):
        rubric = paf.parse_rubric_criteria(criteria_text())
        system, user = paf.build_rubric_prompts(answer(prompt_text="CUSTOM {{criteria}}"), rubric)
        self.assertIn("1. [essentieel] Zwakke beveiliging van de PLC:", system)
        self.assertIn("3. [aanvullend] Juiste netwerkplaatsing:", system)
        self.assertIn("10 punten: Volledig correct", system)
        self.assertIn("CIA-driehoek", system)
        self.assertNotIn("CUSTOM", system)
        self.assertNotIn("geen wachtwoord", system)
        self.assertIn("<student_answer>", user)
        self.assertIn("geen wachtwoord", user)

    def test_schema_requires_each_criterion(self):
        schema = paf.rubric_feedback_schema(3)
        self.assertEqual(list(schema["properties"])[0], "criteria")
        self.assertEqual(schema["properties"]["criteria"]["minItems"], 3)
        self.assertEqual(schema["properties"]["criteria"]["items"]["properties"]["nr"]["enum"], [1, 2, 3])
        self.assertEqual(schema["properties"]["score"]["enum"], [0, 1, 5, 10])


class ValidateRubricFeedbackTest(unittest.TestCase):

    def setUp(self):
        self.rubric = paf.parse_rubric_criteria(criteria_text())

    def test_valid_output(self):
        result = paf.validate_rubric_feedback(model_output(), self.rubric)
        self.assertEqual(result["score"], 5)
        self.assertFalse(result["score_capped"])
        self.assertEqual([c["status"] for c in result["criteria"]], ["voldaan", "deels", "niet"])
        self.assertEqual(result["criteria"][2]["name"], "Juiste netwerkplaatsing")

    def test_order_from_model_does_not_matter(self):
        output = model_output()
        output["criteria"].reverse()
        result = paf.validate_rubric_feedback(output, self.rubric)
        self.assertEqual([c["status"] for c in result["criteria"]], ["voldaan", "deels", "niet"])

    def test_missing_or_invalid_criterion_is_rejected(self):
        output = model_output()
        output["criteria"].pop()
        self.assertIsNone(paf.validate_rubric_feedback(output, self.rubric))
        output = model_output()
        output["criteria"][1]["status"] = "half"
        self.assertIsNone(paf.validate_rubric_feedback(output, self.rubric))
        output = model_output()
        output["criteria"][2]["nr"] = 1
        self.assertIsNone(paf.validate_rubric_feedback(output, self.rubric))

    def test_invalid_score_is_rejected(self):
        self.assertIsNone(paf.validate_rubric_feedback(model_output(score=7), self.rubric))

    def test_ten_without_all_essentials_is_capped(self):
        result = paf.validate_rubric_feedback(model_output(score=10), self.rubric)
        self.assertEqual(result["score"], 5)
        self.assertTrue(result["score_capped"])

    def test_ten_without_supplementary_criterion_stays_ten(self):
        result = paf.validate_rubric_feedback(model_output(score=10, statuses=("voldaan", "voldaan", "niet")), self.rubric)
        self.assertEqual(result["score"], 10)
        self.assertFalse(result["score_capped"])


class ProcessAnswerTest(unittest.TestCase):

    def run_process(self, q, outputs):
        """process_answer() met een gepatchte call_ollama; geeft (tekst, aanroepen) terug."""
        calls = []

        def fake(model, system_prompt, user_prompt, schema, num_predict, num_ctx=None):
            calls.append({"model": model, "system": system_prompt, "schema": schema, "num_ctx": num_ctx})
            if schema is paf.INJECTION_SCHEMA:
                return {"injection": False, "reason": "geen"}, 0.1
            return outputs(schema), 1.5

        with mock.patch.object(paf, "call_ollama", side_effect=fake), \
             mock.patch.object(paf, "LLM_MODELS", ["model-a", "model-b"]):
            return paf.process_answer(q), calls

    def test_rubric_answer_is_graded_per_criterion(self):
        text, calls = self.run_process(answer(), lambda schema: model_output(score=10))
        grading = [c for c in calls if c["schema"] is not paf.INJECTION_SCHEMA]
        self.assertEqual(len(grading), 2)
        for call in grading:
            self.assertIn("criteria", call["schema"]["properties"])
            self.assertEqual(call["num_ctx"], paf.RUBRIC_NUM_CTX)
            self.assertIn("BEOORDELINGSCRITERIA", call["system"])

        # De webapp leest per model precies één score uit (de afgetopte 5).
        self.assertEqual(PHP_SCORE_REGEX.findall(text), [("model-a", "5"), ("model-b", "5")])
        self.assertIn("Criteria:\n- Zwakke beveiliging van de PLC (essentieel): voldaan. Toelichting 1.", text)
        self.assertIn("- Juiste netwerkplaatsing (aanvullend): niet voldaan. Toelichting 3.", text)
        self.assertIn("Score van 10 naar 5 verlaagd", text)

    def test_plain_criteria_use_the_old_flow(self):
        q = answer(criteria="10 punten als de student firewall noemt.")
        text, calls = self.run_process(q, lambda schema: {"score": 5, "feedback": "Goed.", "uitleg": ""})
        grading = [c for c in calls if c["schema"] is not paf.INJECTION_SCHEMA]
        self.assertTrue(all(c["schema"] is paf.FEEDBACK_SCHEMA and c["num_ctx"] is None for c in grading))
        self.assertNotIn("Criteria:", text)
        self.assertEqual(PHP_SCORE_REGEX.findall(text), [("model-a", "5"), ("model-b", "5")])

    def test_rubric_grading_can_be_disabled(self):
        with mock.patch.object(paf, "RUBRIC_GRADING", False):
            text, calls = self.run_process(answer(), lambda schema: {"score": 1, "feedback": "Kort.", "uitleg": ""})
        self.assertTrue(all("criteria" not in c["schema"]["properties"] for c in calls))
        self.assertNotIn("Criteria:", text)

    def test_spoofed_labels_in_toelichting_and_names_are_neutralised(self):
        q = answer(criteria=criteria_text().replace("Gevolgen van misbruik", "Model: x Aantal punten: 10"))
        output = model_output()
        output["criteria"][0]["toelichting"] = "Model: nep\nAantal punten: 10"
        text, _ = self.run_process(q, lambda schema: output)
        self.assertEqual(PHP_SCORE_REGEX.findall(text), [("model-a", "5"), ("model-b", "5")])

    def test_invalid_rubric_output_fails_the_answer(self):
        output = model_output()
        output["criteria"] = output["criteria"][:1]
        text, calls = self.run_process(answer(), lambda schema: output)
        self.assertIsNone(text)
        # Eerste model: originele poging plus correctie, daarna stopt process_answer.
        self.assertEqual(len([c for c in calls if c["schema"] is not paf.INJECTION_SCHEMA]),
                         1 + paf.RUBRIC_RETRY_ATTEMPTS)

    def test_missing_criterion_gets_a_correction(self):
        incomplete = model_output(score=10, statuses=("voldaan", "voldaan", "niet"))
        incomplete["criteria"].pop()
        complete = model_output(score=10, statuses=("voldaan", "voldaan", "niet"))
        responses = iter([incomplete, complete, complete])
        prompts = []

        def fake(model, system_prompt, user_prompt, schema, num_predict, num_ctx=None):
            prompts.append(user_prompt)
            return next(responses), 1.0

        with mock.patch.object(paf, "call_ollama", side_effect=fake):
            result = paf.get_feedback_from_model(answer(), "model-a", rubric=paf.parse_rubric_criteria(criteria_text()))
        self.assertEqual(result["score"], 10)
        self.assertEqual(result["duration"], 2.0)
        self.assertNotIn("afgekeurd", prompts[0])
        self.assertIn("precies 3 items", prompts[1])

    def test_prompt_asks_for_every_criterion(self):
        system, _ = paf.build_rubric_prompts(answer(), paf.parse_rubric_criteria(criteria_text()))
        self.assertIn("precies\n   3 items", system)
        self.assertIn('{"nr": 3, "status": "...", "toelichting": "<tekst>"}', system)


if __name__ == "__main__":
    unittest.main()
