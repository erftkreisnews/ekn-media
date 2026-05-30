# Entscheidungsdokument: Bei ChatGPT bleiben oder zu Claude wechseln?

Stand: 2026-04-23  
Projektkontext: Erftkreis News Media (Laravel 12)

## 1) Ziel des Dokuments

Dieses Dokument dient als externe Entscheidungsgrundlage, ob das Team:
- bei ChatGPT bleibt,
- vollstaendig zu Claude wechselt,
- oder einen aufgabenspezifischen Mischbetrieb nutzt.

Ziel ist **keine Bauchentscheidung**, sondern ein nachvollziehbarer Vergleich auf Basis messbarer Kriterien.

## 2) Kurzfazit (Executive Summary)

Aktuell spricht vieles fuer einen **Mischbetrieb mit klaren Einsatzregeln**:
- ChatGPT fuer schnelle operative Multi-Step-Arbeit und Tool-Workflows,
- Claude fuer laengere, qualitative Doku-/Review-/Spezifikationsaufgaben.

Ein Vollwechsel ist erst sinnvoll, wenn ein strukturierter Pilot zeigt, dass Claude in den priorisierten Aufgabenarten messbar besser ist (Qualitaet, Zeit, Nacharbeit, Teamakzeptanz).

## 3) Ausgangslage im Projekt

Aus dem aktuellen Systemstand:
- KI-nahe Funktionen sind bereits in Admin-Prozessen verankert (u. a. AI-Status/AI-Request).
- Mediennahe Verarbeitung (z. B. Redaction-Pipeline) ist betrieblich stark von Queue/Worker-Stabilitaet abhaengig.
- Es gibt hohe Aenderungsdichte im Repository, daher sind klare, konsistente Assistenz-Outputs wichtig.

Konsequenz: Das LLM muss nicht nur "gut texten", sondern zu den realen Teamablaeufen passen (Tempo, Zuverlaessigkeit, Nachvollziehbarkeit, Review-Qualitaet).

## 4) Vergleich auf einen Blick

## ChatGPT - typische Staerken
- Stark in vielseitigen, schnellen Iterationen.
- Gute Breite bei Coding + Content + operativer Assistenz.
- Sehr geeignet fuer Aufgaben mit vielen kleinen Folgeschritten.

## Claude - typische Staerken
- Sehr stark in langen, zusammenhaengenden Text-/Analyse-Outputs.
- Haeufig hohe Strukturqualitaet bei Architektur- und Review-Begruendungen.
- In sensiblen Kontexten oft defensiver/risikobewusster formuliert.

## Wichtige Einordnung
- Die Unterschiede sind **aufgabenabhaengig**.
- Die richtige Entscheidung ist meist nicht "welches Modell ist generell besser?", sondern "welches Modell ist fuer unsere Kernaufgaben besser?".

## 5) Entscheidungskriterien (messbar)

Bewertet jede Aufgabe mit 1-5 Punkten (5 = sehr gut):

1. **Fachliche Treffgenauigkeit**  
   Wie korrekt und nuetzlich ist das Ergebnis?

2. **Bearbeitungszeit bis nutzbares Ergebnis**  
   Wie schnell ist ein teamfaehiges Resultat da?

3. **Nachbearbeitungsaufwand**  
   Wie viel manuelle Korrektur ist noetig?

4. **Struktur- und Argumentationsqualitaet**  
   Wie nachvollziehbar sind Begruendung und Aufbau?

5. **Operative Zuverlaessigkeit im Tagesgeschaeft**  
   Wie robust ist der Einsatz in echten Arbeitsablaeufen?

6. **Teamakzeptanz**  
   Wie gut kommt das Ergebnis bei den Nutzern an?

Empfehlung Gewichtung:
- Fachliche Treffgenauigkeit: 30%
- Nachbearbeitungsaufwand: 20%
- Bearbeitungszeit: 15%
- Strukturqualitaet: 15%
- Operative Zuverlaessigkeit: 10%
- Teamakzeptanz: 10%

## 6) Pilotvorgehen (2-4 Wochen)

Definiert 3 wiederkehrende Aufgabenarten:
1. Technische Doku-Updates
2. Code-Review-Zusammenfassungen
3. Ticket-/Change-Beschreibungen

Durchfuehrung:
- Jede Aufgabe parallel mit ChatGPT und Claude bearbeiten.
- Gleiche Eingabebasis nutzen (gleicher Promptkontext, gleiche Zieldefinition).
- Ergebnisse blind oder halb-blind bewerten (wenn moeglich).

Zu erfassen pro Aufgabe:
- benoetigte Zeit (Minuten),
- Qualitaetsscore (1-5),
- Nacharbeit (Minuten),
- Reviewer-Kommentar (kurz),
- Tool/Modell.

## 7) Entscheidungslogik nach dem Piloten

## Option A - Bei ChatGPT bleiben
Wenn ChatGPT bei euren Kernaufgaben insgesamt gleich gut oder besser ist und weniger Umstellungsaufwand erzeugt.

## Option B - Vollwechsel zu Claude
Wenn Claude in den priorisierten Aufgabenarten konsistent besser scored (v. a. Qualitaet + weniger Nacharbeit) und der Teamfit klar ist.

## Option C - Mischbetrieb (haeufig sinnvoll)
Wenn beide in unterschiedlichen Aufgabentypen sichtbar staerker sind.

Beispiel-Regelwerk:
- "Schnelle operative Workflows" -> ChatGPT
- "Lange Spezifikationen/Reviews/Doku" -> Claude

## 8) Risiken bei der Entscheidung

- Entscheidung nur aus Einzelbeispielen statt aus Serienvergleich.
- Vergleich mit ungleichen Prompts oder ungleichem Kontext.
- Fokus nur auf Geschwindigkeit statt auf Gesamtkosten (inkl. Nacharbeit).
- Zu frueher Vollwechsel ohne Team-Onboarding.

## 9) Konkrete Empfehlung fuer das Management

1. Kein sofortiger Vollwechsel.  
2. 2-4 Wochen Pilot mit klaren Metriken.  
3. Danach datenbasierte Entscheidung zwischen Verbleib, Wechsel oder Mischbetrieb.  
4. Bei Mischbetrieb: verbindliche Einsatzregeln pro Aufgabentyp definieren.

---

Kontaktpunkt intern: Dieses Dokument basiert auf dem aktuellen technischen Systemstand des Projekts und dient als Entscheidungsgrundlage fuer externe Abstimmung.
