' Launches start-ngrok.bat with no visible window (used by the scheduled task
' so the GuideMate tunnel comes up automatically at login and stays online).
Dim shell, here
Set shell = CreateObject("WScript.Shell")
here = Left(WScript.ScriptFullName, InStrRev(WScript.ScriptFullName, "\"))
shell.Run """" & here & "start-ngrok.bat""", 0, False
