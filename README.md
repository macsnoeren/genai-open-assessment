# genai-open-assessment
**Automated, rubric-driven grading of open knowledge questions using generative AI**

## Overview
*genai-open-assessment* is a research-informed framework and reference implementation for using generative AI (genAI) to automatically assess open-ended knowledge questions in higher education.

The project demonstrates how open questions — traditionally avoided due to grading workload — can be reintroduced at scale by using generative AI as a **constrained, rubric-driven assessor**. Student answers are evaluated against explicitly defined criteria, scored using a fixed grading scale, and provided with concise formative feedback.

The system is designed with **educational validity, transparency, and auditability** as core principles. All prompts, criteria, inputs, and outputs can be stored and reviewed, ensuring that human educators remain fully accountable for assessment decisions.

---

## Key principles
- **Open questions as primary assessment form**  
  Focus on conceptual understanding and knowledge construction rather than recognition.
- **Rubric-driven AI grading**  
  Educators define assessment criteria, scoring rules, and feedback constraints.
- **Prompt engineering as assessment design**  
  Prompts operationalise learning objectives and grading rubrics.
- **Structured and auditable output**  
  AI responses follow a fixed JSON format for reproducibility and review.
- **Human responsibility by design**  
  AI executes the assessment logic; educators retain ownership and oversight.

---

## Why this project exists
Traditional automated assessment relies heavily on closed questions (e.g. multiple choice), which are efficient but often insufficient for measuring deep understanding.

Open-ended questions:
- better reflect learning objectives,
- require students to articulate knowledge,
- but are expensive and time-consuming to grade.

Recent advances in generative AI make it possible to **automate the grading of open answers** — *if* the system is carefully constrained. This project explores how genAI can be used responsibly to reduce grading workload **without sacrificing validity or control**.

---

## Conceptual model
1. The educator designs:
   - the question,
   - the correct answer or rubric,
   - the scoring scale (e.g. 0 / 1 / 5 / 10),
   - feedback constraints.
2. These elements are translated into a **strict prompt**.
3. The genAI model:
   - compares the student answer to the criteria,
   - assigns a score,
   - generates short, student-facing feedback.
4. All inputs and outputs are logged for review and audit.

The AI does **not** decide *what* is correct — it executes the logic defined by the educator.

---

## Prompt design (core component)
Prompt engineering is treated as an integral part of assessment design.

Effective prompts in this project:
- define **one task only** (grading),
- explicitly forbid analysis or explanation,
- constrain the output format (JSON),
- limit the allowed score values,
- restrict feedback length and tone.

Example (simplified):

```text
You are an automated grading system.
You may NOT provide explanations or analysis.

TASK:
- Grade the student answer.
- Assign a score: 0, 1, 5, or 10.
- Provide short feedback in the second person ("je").

OUTPUT (JSON only):
{ "score": <0-10>, "feedback": "<text>" }
```

This approach significantly reduces output variability and increases reproducibility, especially when using smaller or locally hosted models.

---

## Output format
All assessments return **machine-readable JSON**:

```json
{
  "score": 5,
  "feedback": "Je antwoord laat zien dat je de basis begrijpt, maar mist nog belangrijke details."
}
```

This enables:
- automatic processing,
- storage and auditing,
- human review and moderation,
- integration with existing assessment systems.

---

## Intended audience
- Higher education educators and instructional designers  
- Examination committees and quality assurance professionals  
- Educational researchers exploring AI-based assessment  
- Developers building privacy-aware grading pipelines (e.g. with local LLMs)

---

## Academic foundation
This project is grounded in current research and policy guidance on:
- automated assessment of open-ended responses,
- rubric-aligned grading with large language models,
- constructive alignment and assessment validity,
- responsible use of AI in education.

The accompanying academic paper provides the full theoretical framework and literature review.

---

## Ethical and educational considerations
- AI is used as a **support tool**, not as an autonomous examiner.
- Educators remain responsible for:
  - assessment design,
  - validity,
  - fairness,
  - final accountability.
- All assessments are transparent and traceable.
- The system is compatible with local deployment to support data privacy.

---

## Limitations
- GenAI output quality depends on prompt quality and rubric clarity.
- Periodic human validation remains necessary.
- Language models may exhibit bias or variability and should not be treated as infallible.
- This approach is best suited for **knowledge-oriented open questions**, not complex creative or affective assessments.

---

## Future work
- Empirical validation of inter-rater reliability (AI vs human)
- Student perception and learning impact studies
- Longitudinal effects on assessment design
- Integration with LMS and digital exam platforms

---

## Citation
If you use this project in research or practice, please cite the accompanying paper:

> *The use of generative AI for automated assessment of open-ended knowledge questions in higher education.*

---

## License
This project is intended for educational and research use.  
Please review the license file for details.
