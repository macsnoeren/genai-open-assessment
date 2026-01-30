# Copyright (C) 2025 JMNL Innovation.
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.

import win32serviceutil
import win32service
import win32event
import servicemanager
import socket
import time
import sys
import os

# Zorg dat we modules uit de huidige map kunnen importeren
current_dir = os.path.dirname(os.path.abspath(__file__))
sys.path.append(current_dir)

from config import LLM_MODELS, POLL_INTERVAL
from process_ai_feedback import fetch_open_student_answers, get_feedback_from_model, submit_ai_feedback

class GenAIFeedbackService(win32serviceutil.ServiceFramework):
    _svc_name_ = "GenAIFeedbackService"
    _svc_display_name_ = "GenAI Open Assessment Feedback Service"
    _svc_description_ = "Service voor het automatisch beoordelen van open vragen met lokale LLM's."

    def __init__(self, args):
        win32serviceutil.ServiceFramework.__init__(self, args)
        self.hWaitStop = win32event.CreateEvent(None, 0, 0, None)
        socket.setdefaulttimeout(60)
        self.is_running = True

    def SvcStop(self):
        self.ReportServiceStatus(win32service.SERVICE_STOP_PENDING)
        win32event.SetEvent(self.hWaitStop)
        self.is_running = False

    def SvcDoRun(self):
        servicemanager.LogMsg(servicemanager.EVENTLOG_INFORMATION_TYPE,
                              servicemanager.PYS_SERVICE_STARTED,
                              (self._svc_name_, ''))
        self.main()

    def main(self):
        # Redirect stdout/stderr om crashes te voorkomen als er geen console is
        sys.stdout = open(os.devnull, 'w')
        sys.stderr = open(os.devnull, 'w')

        while self.is_running:
            try:
                # Wacht op stop signaal of tot POLL_INTERVAL voorbij is
                # POLL_INTERVAL is in seconden, WaitForSingleObject verwacht milliseconden
                rc = win32event.WaitForSingleObject(self.hWaitStop, int(POLL_INTERVAL * 1000))
                
                if rc == win32event.WAIT_OBJECT_0:
                    # Stop signaal ontvangen
                    break

                # Logica uitvoeren (overgenomen uit process_ai_feedback.py)
                answers = fetch_open_student_answers()
                
                if answers:
                    for q in answers:
                        if not self.is_running: break
                        
                        all_feedback = []
                        failed = False

                        for model in LLM_MODELS:
                            if not self.is_running: break
                            
                            result = get_feedback_from_model(q, model)

                            if result:
                                all_feedback.append(
                                    f"Model: {model}\n"
                                    f"Tijdsduur: {result['duration']:.2f}s\n"
                                    f"Aantal punten: {result['score']}\n"
                                    f"Feedback: {result['feedback']}\n"
                                    f"Uitleg: {result['uitleg']}"
                                )
                            else:
                                servicemanager.LogInfoMsg(f"Model {model} faalde voor antwoord {q['student_answer_id']}")
                                failed = True
                                break

                        if failed or not all_feedback:
                            continue

                        final_feedback = "\n\n".join(all_feedback)

                        submit_ai_feedback(
                            student_answer_id=q["student_answer_id"],
                            feedback_text=final_feedback
                        )
                        
                        servicemanager.LogInfoMsg(f"Feedback verstuurd voor student_answer_id {q['student_answer_id']}")

            except Exception as e:
                servicemanager.LogErrorMsg(f"Fout in service loop: {str(e)}")
                time.sleep(5)

if __name__ == '__main__':
    win32serviceutil.HandleCommandLine(GenAIFeedbackService)