Überprüfung der Protokollierung
Jede ändernde Nutzerinteraktion sollte protokolliert werden. Überprüfe in dne Controllern ob dies der Fall ist und ergänze ggf. die notwendigen Protokollierungen.
Schreibe einen Test zu jedem Log-Aufruf. Prüfe dabei ob der Eintrag sowohl in der Tenant als auch in der Central DB gespeichert wurde.

Jede Controller-Aktivität sollte gegen die vergebenen Rechte geprüft werden. Bitte prüfe ob in jeder Funktion die entsprechende Abfrage vorhanden ist und ergänze diese in den Funktionen.

Erstelle für jede Rolle einen Test, der Prüft ob die Rolle genau zu den Controller-Aktivitäten hat, die auch entsprechend der Geseedeten Permissions vorgesehen sind.
