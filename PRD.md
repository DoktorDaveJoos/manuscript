# Manuscript AI — Product Requirements Document

**Version:** 1.0  
**Status:** Draft — Intern  
**Autor:** Allgäu Digitalwerk  
**Stack:** NativePHP · Laravel · SQLite · sqlite-vec · React  
**Monetarisierung:** One-Time Purchase + Spenden · Bring Your Own API Key

---

## 1. Produktvision

Manuscript AI ist ein Desktop-Tool für ernstzunehmende Autoren — insbesondere Self-Publisher — das ihnen ermöglicht, ihre eigene Geschichte zu besitzen, zu kontrollieren und zu verbessern, ohne auf Cloud-Dienste angewiesen zu sein.

Der Kern-Insight: Gute Autoren haben Instinkt für Story, Struktur und Dialoge. Was ihnen fehlt, ist Prosa-Konsistenz, Pacing-Kontrolle und ein Werkzeug, das den Überblick über einen komplexen Roman behält. Manuscript AI löst genau das — mit AI als optionalem Assistenten, nicht als Voraussetzung.

Das Tool läuft vollständig lokal. Der Autor ist Owner seiner Geschichte. Keine Daten verlassen das Gerät. AI-Features sind opt-in über einen selbst verwalteten API Key.

---

## 2. Zielgruppe

**Primär**
- Self-Publisher auf Amazon KDP die Serienromane produzieren (Romance, Fantasy, Thriller)
- Autoren mit starkem Storytelling-Instinkt aber inkonsistenter Prosa
- Autoren die komplexe Multi-Storyline-Romane mit mehreren POVs schreiben
- Autoren die ihren Stil konsistent halten wollen ohne ihn aufzugeben
- Einsteiger-Autoren die handwerkliche Grundlagen erlernen wollen

**Sekundär**
- Hobbyautoren die professioneller werden wollen
- Schreibgruppen die gemeinsam an einem Projekt arbeiten (spätere Phase)

**Nicht-Zielgruppe**
- Autoren die wollen dass AI den Roman schreibt — das Tool ist kein Ghostwriter
- Verlage und professionelle Lektoren — andere Tool-Kategorie

---

## 3. Kernprinzipien

| Prinzip | Beschreibung |
|---|---|
| **Offline-First** | Das Tool funktioniert vollständig ohne Internetverbindung. Kein Account, keine Cloud, keine Abhängigkeit von externen Services. |
| **AI als Opt-In** | Alle AI-Features sind deaktiviert solange kein API Key hinterlegt ist. Jede Kernfunktion — Versionierung, Plot-Verwaltung, Charakter-Datenbank — funktioniert ohne AI. |
| **Autor bleibt Author** | AI überarbeitet Prosa, erfindet nichts. Dialoge, Struktur und emotionale Architektur kommen immer vom Autor. |
| **Own Your Story** | Alle Daten liegen in einer lokalen SQLite-Datei. Der Autor kann diese Datei kopieren, sichern, teilen oder löschen — vollständige Kontrolle. |
| **BYOK** | Bring Your Own API Key. Keine versteckten Kosten, kein Abo für AI-Features. Der Autor zahlt direkt an Anthropic/OpenAI. |
| **Mehrere Bücher** | Das Tool ist von Anfang an für mehrere Bücher ausgelegt. Performance und Datenstruktur skalieren entsprechend. |
| **Handwerk lehrbar machen** | Das Tool erklärt warum etwas ein Problem ist — nicht nur dass es eines ist. Besonders für Einsteiger. |

---

## 4. Was einen guten Thriller ausmacht — Handwerkliche Grundlagen

Dieser Abschnitt dokumentiert die Genre-Spezifika die als Basis für die AI-Analyse-Engine dienen.

### 4.1 Die drei Kern-Elemente jedes Thrillers

- **Core Need: Sicherheit** — Im Subtext jedes Thrillers steht die universelle menschliche Sehnsucht nach Sicherheit. Diese wird von der ersten Seite an bedroht.
- **Core Value: Ein Schicksal schlimmer als der Tod** — Die Stakes gehen über physischen Tod hinaus: Identitätsverlust, Verlust geliebter Menschen, moralisches Scheitern.
- **Core Emotion: Dread** — Nicht Schreck (das ist Horror), sondern anhaltende Anspannung, Nervosität, der unbändige Wunsch weiterzulesen.

### 4.2 Strukturelle Gesetze des Genres

**Jede Szene muss arbeiten.** Anders als andere Genres muss im Thriller jede einzelne Szene die Handlung vorwärtstreiben — entweder durch Demonstration der hohen Stakes oder durch eine direkte Herausforderung des Protagonisten. Szenen die nur "Atmosphäre" schaffen ohne Plot- oder Charakter-Funktion sind im Thriller tödlich.

**Pacing ist die unsichtbare Architektur.** Rückblenden, Träume und biografische Einschübe unterbrechen den Spannungsfluss. Sie können funktionieren — aber nur wenn sie präzise platziert sind und selbst einen Informationsgewinn liefern.

**Cliffhanger sind kein Trick — sie sind Struktur.** Jedes Kapitelende sollte einen offenen Hook haben. Nicht immer eine Explosion, aber immer eine offene Frage, eine Wendung, eine Bedrohung die sich konkretisiert.

**Steigende Stakes pro Akt.** Die Bedrohung muss mit jedem Akt größer, persönlicher oder unlösbarer werden. Flache Stakes = flacher Thriller.

### 4.3 Charakter-Anforderungen

- **Protagonist:** Gewöhnlich, fehlerhaft, mit dem Leser identifizierbar — kein Superman. Die Heldenreise entsteht durch Wachstum unter extremem Druck.
- **Antagonist:** Mindestens so intelligent, resourceful und entschlossen wie der Protagonist. Hat eigene Motivation die unabhängig vom Protagonisten existiert. Ein schwacher Villain macht automatisch einen schwachen Thriller.
- **Nebencharaktere:** Jeder Charakter braucht eine Funktion. Figuren die nur Plot-Vehikel sind, fühlen sich flach an.

### 4.4 Die häufigsten Fehler unerfahrener Thriller-Autoren

1. **Telling not Showing** — Emotionen werden erklärt statt erfahrbar gemacht
2. **Zu langsamer Anfang** — Weltenbau und Biografie vor dem ersten Konflikt
3. **Funktionslose Szenen** — Der Autor liebt sie, der Leser langweilt sich
4. **Pacing-Blindheit** — Mehrere ruhige Kapitel hintereinander ohne Bewusstsein dafür
5. **Schwacher Antagonist** — Taucht auf wenn der Plot es braucht, hat keine eigene Agenda
6. **Charakter-Inkonsistenz** — Figuren verhalten sich wie der Plot es braucht, nicht wie sie es täten
7. **Passive Konstruktionen und Adverbien** — Bremsen Tempo und Direktheit
8. **Keine klaren Stakes** — Der Leser weiß nicht was auf dem Spiel steht

---

## 5. Feature-Übersicht

### 5.1 Buch-Management
- Mehrere Bücher pro Installation
- Pro Buch: Titel, Sprache, optionaler API Key (verschlüsselt gespeichert)
- Import via `.docx` — Heading 1 als Kapitel-Trennzeichen
- Export: `.docx`, `.txt` (spätere Phase: ePub, PDF)

### 5.2 Ingest & RAG

Beim Upload wird das Manuskript automatisch verarbeitet:

- Einlesen der `.docx` Datei, Extraktion des Rohtexts
- Aufteilen in Kapitel anhand von Heading-1-Überschriften
- Chunking: je ~500 Wörter mit 10% Überlappung für Kontext
- Embedding-Generierung via OpenAI `text-embedding-3-small` (nur mit API Key)
- Speicherung in `sqlite-vec` — semantische Suche über den gesamten Roman

Ohne API Key: Kapitel werden gespeichert, Embeddings werden übersprungen. Alle anderen Features bleiben verfügbar.

### 5.3 Storyline-Verwaltung
- Jedes Buch hat eine oder mehrere Storylines
- Typen: `main` | `backstory` | `parallel`
- Jede Storyline hat einen Timeline-Label (z.B. "2062", "2058–2060")
- Kapitel werden einer Storyline zugeordnet
- Analyse und Editor laufen pro Storyline und übergreifend
- Backstory-Kapitel werden in einer separaten "Backstory-Bibliothek" angezeigt
- Lesereihenfolge-Planung (Einstreuung von Backstory-Kapiteln): Phase 3

### 5.4 Akt-Struktur
- Optional: Autor kann Akte definieren (nicht auf 3 limitiert)
- Kapitel und Plot Points können Akten zugeordnet werden
- Canvas visualisiert Akte als farbige Blöcke auf der X-Achse
- AI kann prüfen ob Akt-Balance stimmt (z.B. Akt 2 zu kurz, Stakes steigen nicht)

### 5.5 Charakter-Datenbank
- Manuelle Anlage: Name, Aliases, Beschreibung, erstes Auftreten
- Mit AI Key: automatische Extraktion aus Kapiteln
- Charakter-Karten: wer taucht in welchem Kapitel auf, in welcher Rolle
- Beziehungs-Tracking: Phase 3
- Cross-Storyline: Charakter kann in mehreren Storylines auftreten

### 5.6 Plot-Verwaltung
- Plot Points manuell anlegen: Titel, Beschreibung, Typ, geplantem Kapitel
- Typen: `setup` | `conflict` | `turning_point` | `resolution` | `worldbuilding`
- Mit AI Key: Plot Points automatisch aus Kapiteln ableiten
- AI prüft: wurde dieser Plot Point erfüllt? In welchem Kapitel tatsächlich passiert?
- Status: `planned` | `fulfilled` | `abandoned`
- Plot kann leer bleiben — dann leitet AI alles aus dem geschriebenen Material ab

### 5.7 Story Heartbeat — Thriller Command Center

Der Canvas ist kein einfaches Diagramm — er ist das visuelle Kommandozentrum des Romans. Vier gestapelte Analyse-Spuren zeigen auf einen Blick wo der Roman funktioniert und wo Leser abspringen.

**Kern-Insight:** Leser hören an Kapitelenden auf zu lesen. Das Einzige was einen Page-Turner von einem Put-Downer unterscheidet ist wie jedes Kapitel endet. Kein anderes Schreib-Tool visualisiert das.

#### 5.7.1 Lane-System

Vier horizontale Spuren übereinander, alle teilen die X-Achse (Kapitel in Lesereihenfolge):

| Spur | Inhalt | Zweck |
|------|--------|-------|
| **Tension Arc** | Spannungskurve 1–10 über alle Kapitel mit Akt-Blöcken, Plot-Point-Markern, Storyline-Spuren | Der "Puls" der Geschichte — zeigt den dramaturgischen Bogen |
| **Chapter Hook Score** | Farbige Blöcke die jedes Kapitelende bewerten: `cliffhanger` · `soft_hook` · `closed` · `dead_end` | **Die Killer-Funktion** — zeigt exakt wo Leser aufhören weiterzulesen. Eine Reihe grüner Blöcke mit einem roten = sofort behebbares Problem |
| **Pacing Rhythm** | Proportionale Balken die Kapitel-Wortzahlen darstellen | Visuelles Tempo-Muster — monotone gleich-lange Kapitel vs. gute Kurz-Lang-Variation |
| **Storyline Weave** | Farbige Bänder die zeigen welcher POV/Storyline jedes Kapitel gehört | Zeigt ob Storylines gut verwoben sind oder ob ein POV zu lange dominiert |

**Tension Arc (oberste Spur):**
- Y-Achse: Spannungsintensität 1–10 (AI-bewertet, manuell überschreibbar)
- Akte als farbige Hintergrund-Blöcke
- Plot Points als Pins auf der Kapitel-Position
- Stakes-Kurve als optionales Overlay (Toggle)
- Villain-Screentime als optionales Overlay (Toggle)

**Chapter Hook Score:**
- Pro Kapitel ein farbiger Block mit Score-Zahl
- Farbkodierung: `cliffhanger` = Amber/Warm · `soft_hook` = Grün · `closed` = Grau · `dead_end` = Rot
- AI-Reasoning pro Hook verfügbar (Klick öffnet Detail)
- Warnung wenn aufeinanderfolgende Kapitel `closed` oder `dead_end` sind

**Pacing Rhythm:**
- Balken-Höhe proportional zur Wortzahl des Kapitels
- Durchschnittslinie als Referenz
- Ausreißer visuell hervorgehoben (zu kurz / zu lang)

**Storyline Weave:**
- Farbige Bänder pro Kapitel — Farbe = Storyline
- POV-Initialen im Band
- Warnung wenn eine Storyline mehr als 3 Kapitel hintereinander dominiert

#### 5.7.2 Thriller Health Dashboard

Rechtes Panel mit aggregierter Gesundheitsanalyse:

- **Health Score (0–100):** Gewichteter Gesamtwert
  - Hooks: 35% — Kapitelschluss-Qualität ist der stärkste Faktor
  - Pacing: 25% — Rhythmus und Wortzahl-Variation
  - Tension: 25% — Spannungskurven-Verlauf und Progression
  - Weave: 15% — Storyline-Verteilung und POV-Balance
- **Top 3 schwächste Hooks:** Klickbar, mit AI-Reasoning warum der Hook schwach ist
- **Pacing-Alerts:** Monotonie-Warnung, Ausreißer, ruhige Kapitel-Serien
- **Next Action:** Konkreter Vorschlag was der Autor als nächstes tun sollte

#### 5.7.3 Lane-Steuerung

- Toggle-Pills: Tension · Hooks · Pacing · Weave (einzeln ein-/ausblendbar)
- Sekundäre Toggles: Stakes-Overlay · Villain-Overlay (nur auf Tension Arc)
- Storyline-Filter: "Alle" oder spezifische Storyline
- Kapitel-Klick: öffnet Detail-Panel mit AI-Analyse für dieses Kapitel

### 5.8 Kapitel-Versionierung
- Jedes Kapitel hat eine vollständige Versionshistorie
- Versionsquellen: `original` | `ai_revision` | `manual_edit`
- Jede Version hat einen Timestamp und eine Zusammenfassung der Änderungen
- Aktive Version ist markiert — Wiederherstellen per Klick
- Diff-Ansicht: beliebige zwei Versionen vergleichen

### 5.9 AI Prosa-Editor

*Nur mit API Key.* Überarbeitet ein Kapitel auf Prosa-Ebene:

- Kontext wird automatisch zusammengestellt: Story Bible + Charakter-Cards + vorheriges Kapitel-Summary
- Dialoge bleiben exakt unverändert
- Emotionale Struktur und Dramaturgie bleiben vollständig erhalten
- Wiederholungen eliminieren, Rhythmus verbessern, Grammatik korrigieren
- Logik-Inkonsistenzen innerhalb der Szene erkennen und elegant lösen
- Expositorische Einschübe rhythmisch besser integrieren
- Ergebnis als neue Version gespeichert — Diff-Ansicht, Akzeptieren oder Ablehnen

---

## 6. AI Analyse-Engine (Thriller-optimiert)

*Nur mit API Key. Läuft beim Ingest und auf Abruf.*

### 6.1 Szenen-Audit

Für jede Szene automatisch prüfen:

- **Funktion:** Was tut diese Szene? Plot-Fortschritt, Charakter-Entwicklung, Stakes-Demonstration?
- **Informationsgehalt:** Welche neue Information bekommt der Leser?
- **Ergebnis:** Szenen ohne nachweisbare Funktion werden rot markiert — mit Erklärung warum und Vorschlag wie man das behebt

### 6.2 Thriller-Stil-Check

Automatisches Markieren von:

- Passiv-Konstruktionen ("wurde gesehen", "war zu hören")
- Adverbien die Direktheit bremsen
- "Telling"-Phrasen: "er fühlte", "er wusste dass", "sie dachte" — wo Showing möglich wäre
- Lange Exposition-Blöcke in Action-Szenen
- Biografische Einschübe die den Spannungsfluss unterbrechen

Jede Markierung enthält eine Erklärung und einen konkreten Überarbeitungsvorschlag — nicht nur ein Flag.

### 6.3 Kapitelschluss-Analyse

- Endet das Kapitel mit einem offenen Hook?
- Klassifizierung: `cliffhanger` | `soft_hook` | `closed` | `unresolved_question`
- Warnung wenn mehrere aufeinanderfolgende Kapitel mit `closed` enden
- Stärke-Scoring im Vergleich zu anderen Kapiteln des Buchs

### 6.4 Pacing-Coach

- Spannungskurve über alle Kapitel visualisiert
- Warnung bei drei oder mehr ruhigen Kapiteln in Folge
- Wortzahl pro Kapitel — Ausreißer (zu kurz / zu lang) markiert
- Action/Dialog/Beschreibung-Ratio pro Kapitel
- Konkrete Empfehlungen: "Hier drei ruhige Kapitel — erwäge einen Reveal oder eine Konfrontation"

### 6.5 Plothole-Detection

- Widersprüche zwischen Kapitel-Summaries automatisch erkennen
- Zeitlinien-Inkonsistenzen (besonders bei Multi-Storyline-Projekten)
- Charakter-Wissen-Tracking: weiß ein Charakter etwas das er zu diesem Zeitpunkt noch nicht wissen kann?
- Cross-Storyline-Konsistenz: widerspricht die Backstory etwas in der Hauptlinie?

### 6.6 Charakter-Konsistenz-Check

- Charakter-Profil wird gegen tatsächliches Verhalten in jedem Kapitel geprüft
- Flagging wenn eine Figur sich ohne nachvollziehbare Entwicklung anders verhält als etabliert
- Beispiel: "Arik hat in Kapitel 3 etabliert dass er X niemals tut — in Kapitel 11 tut er es ohne Erklärung"

### 6.7 Villain-Dashboard

- Screentime des Antagonisten pro Kapitel und Akt
- Verhältnis aktiv/reaktiv: handelt der Villain selbst oder reagiert er nur?
- Motivations-Kohärenz: hat der Antagonist eine eigene Agenda die unabhängig vom Protagonisten existiert?
- Warnung wenn der Villain für mehr als X Kapitel komplett verschwindet

### 6.8 Stakes-Tracker

- Sind die Stakes in Kapitel 1 klar definiert?
- Steigen die Stakes pro Akt?
- Werden die Stakes regelmäßig dem Leser in Erinnerung gerufen?
- Vergleich Stakes-Kurve vs. Spannungs-Kurve: laufen sie synchron?

### 6.9 Dichte-Analyse ("Zu knapp"-Detection)

- Szenen die wichtige Plot-Momente nur streifen statt auszuformulieren
- Vergleich Wortzahl vs. Plot-Point-Gewicht
- Vorschlag: "Diese Szene trägt Turning Point X — sie hat aber nur 400 Wörter. Erwäge mehr Raum zu geben."

### 6.10 Erster-Kapitel-Audit

Spezialisierte Analyse für Kapitel 1:

- Wann taucht der erste echte Konflikt auf? (Benchmark: spätestens Seite 3)
- Wann werden die Stakes klar?
- Gibt es biografische Exposition oder Worldbuilding vor dem ersten Konflikt? (Warnung)
- Wird Protagonist und Antagonist eingeführt?
- Hook-Stärke der Eröffnungszeile

### 6.11 Nächstes-Kapitel-Vorschlag

- Basierend auf aktuellem Stand: was sollte das nächste Kapitel leisten?
- Welche Plot Points sind noch offen?
- Welcher Charakter wurde zu lange nicht gezeigt?
- Was braucht die Spannungskurve an dieser Stelle?

---

## 7. Tech Stack

| Komponente | Entscheidung |
|---|---|
| **Desktop-Framework** | NativePHP — bundled PHP + SQLite als native `.dmg` / `.exe` / `.AppImage` |
| **Backend** | Laravel (PHP) — API-Routes, Queue-Jobs für Analyse, Eloquent ORM |
| **Datenbank** | SQLite mit `sqlite-vec` Extension — Vektor-Suche lokal, kein externer Service |
| **Frontend** | React via Inertia.js — SPA-Feel ohne separaten Build-Prozess. Tailwind CSS. |
| **Vektorisierung** | OpenAI `text-embedding-3-small` — günstig, stabil, ausreichend für Roman-Scope |
| **AI-Modell** | Claude Sonnet (Anthropic) für Analyse und Prosa. GPT-4o als Alternative. |
| **Plattformen** | macOS, Windows, Linux via NativePHP Build-Pipeline |
| **Import/Export** | `phpoffice/phpword` für `.docx` Parsing und Export |

### sqlite-vec Bundle-Strategie

sqlite-vec wird als plattformspezifische Binary mitgeliefert:

- `sqlite-vec-macos.dylib`
- `sqlite-vec-windows.dll`
- `sqlite-vec-linux.so`

Wird beim App-Start transparent geladen:

```php
// AppServiceProvider::boot()
$extensionPath = base_path('bin/sqlite-vec-' . PHP_OS_FAMILY . '.so');
DB::statement("SELECT load_extension('$extensionPath')");
```

---

## 8. Datenstruktur

Alle Daten liegen in einer einzigen SQLite-Datei pro Installation.

### Übersicht

```
books
  └── storylines
  └── acts
  └── characters
  └── chapters
        └── act_id (FK)
        └── storyline_id (FK)
        └── pov_character (FK)
        └── chapter_versions     ← Versionierung
              └── chunks         ← RAG, nur mit API Key
        └── character_chapter    ← Pivot
  └── plot_points
        └── act_id (FK)
        └── storyline_id (FK)
        └── intended_chapter (FK)
        └── actual_chapter (FK)
  └── analyses
```

### books

```sql
id              INTEGER PRIMARY KEY
title           TEXT NOT NULL
author          TEXT
language        TEXT DEFAULT 'de'
api_key         TEXT NULL              -- encrypted, null = AI disabled
ai_provider     TEXT DEFAULT 'anthropic' -- anthropic | openai
ai_enabled      BOOLEAN DEFAULT FALSE
created_at      DATETIME
updated_at      DATETIME
```

### storylines

```sql
id              INTEGER PRIMARY KEY
book_id         INTEGER FK books
name            TEXT NOT NULL          -- 'Hauptlinie', 'Arik Backstory'
type            TEXT                   -- main | backstory | parallel
timeline_label  TEXT                   -- '2062', '2058-2060'
color           TEXT                   -- Hex für Canvas
sort_order      INTEGER
created_at      DATETIME
```

### acts

```sql
id              INTEGER PRIMARY KEY
book_id         INTEGER FK books
number          INTEGER                -- 1, 2, 3 ...
title           TEXT                   -- 'Akt 1 - Der Aufbruch'
description     TEXT NULL
color           TEXT                   -- Hex für Canvas
sort_order      INTEGER
created_at      DATETIME
```

### characters

```sql
id              INTEGER PRIMARY KEY
book_id         INTEGER FK books
name            TEXT NOT NULL
aliases         TEXT                   -- JSON Array
description     TEXT
role            TEXT                   -- protagonist | antagonist | supporting
first_appearance INTEGER FK chapters NULL
storylines      TEXT                   -- JSON Array von storyline_ids
is_ai_extracted BOOLEAN DEFAULT FALSE
created_at      DATETIME
updated_at      DATETIME
```

### chapters

```sql
id              INTEGER PRIMARY KEY
book_id         INTEGER FK books
storyline_id    INTEGER FK storylines
act_id          INTEGER FK acts NULL
title           TEXT NOT NULL
pov_character   INTEGER FK characters NULL
timeline_position TEXT               -- Datum/Label innerhalb Storyline
reader_order    INTEGER NULL         -- geplante Lesereihenfolge
status          TEXT DEFAULT 'draft' -- draft | revised | final
word_count      INTEGER DEFAULT 0
tension_score   INTEGER NULL         -- 1-10, AI oder manuell
hook_score      INTEGER NULL         -- 1-10, Stärke des Kapitelschlusses
hook_type       TEXT NULL            -- cliffhanger | soft_hook | closed | dead_end
created_at      DATETIME
updated_at      DATETIME
```

### chapter_versions

```sql
id              INTEGER PRIMARY KEY
chapter_id      INTEGER FK chapters
version_number  INTEGER NOT NULL
content         TEXT NOT NULL          -- Volltext dieser Version
source          TEXT                   -- original | ai_revision | manual_edit
change_summary  TEXT                   -- 'AI Prosa-Pass' / 'Manuelle Überarb.'
is_current      BOOLEAN DEFAULT FALSE
created_at      DATETIME
```

### chunks

```sql
id              INTEGER PRIMARY KEY
chapter_version_id INTEGER FK chapter_versions
content         TEXT NOT NULL
position        INTEGER                -- Reihenfolge im Kapitel
embedding       F32_BLOB(1536)         -- sqlite-vec Vektorspalte, NULL ohne API Key
```

### plot_points

```sql
id              INTEGER PRIMARY KEY
book_id         INTEGER FK books
storyline_id    INTEGER FK storylines NULL  -- NULL = übergreifend
act_id          INTEGER FK acts NULL
title           TEXT NOT NULL
description     TEXT
type            TEXT
                -- setup | conflict | turning_point | resolution | worldbuilding
intended_chapter INTEGER FK chapters NULL
actual_chapter  INTEGER FK chapters NULL
status          TEXT DEFAULT 'planned'
                -- planned | fulfilled | abandoned
sort_order      INTEGER
is_ai_derived   BOOLEAN DEFAULT FALSE
created_at      DATETIME
```

### character_chapter (Pivot)

```sql
id              INTEGER PRIMARY KEY
character_id    INTEGER FK characters
chapter_id      INTEGER FK chapters
role            TEXT                   -- protagonist | supporting | mentioned
notes           TEXT
```

### analyses

```sql
id              INTEGER PRIMARY KEY
book_id         INTEGER FK books
chapter_id      INTEGER FK chapters NULL  -- NULL = Buch-gesamt
type            TEXT
                -- pacing | plothole | character_consistency | density
                -- plot_deviation | scene_audit | style_check
                -- chapter_ending | chapter_hook | villain_screentime | stakes
                -- first_chapter | next_chapter_suggestion | thriller_health
result          TEXT                   -- JSON mit Findings
ai_generated    BOOLEAN DEFAULT FALSE
created_at      DATETIME
```

---

## 9. AI-Architektur

### 9.1 Hierarchisches Context Management

| Ebene | Inhalt | Tokens | Wann |
|---|---|---|---|
| **Story Bible** | Charaktere, Setting, Plot-Outline, Stil-Regeln, Genre-spezifische Regeln | ~5.000 | Immer |
| **Kapitel-Summaries** | Komprimiert was bisher geschah | ~3.000 | Immer |
| **Aktives Kapitel** | Volltext aktuelles + vorheriges Kapitel | ~10.000 | Immer |
| **RAG** | Semantische Suche für spezifische Details | ~2.000 | On Demand |

Resultat: ~20.000 Tokens pro Call statt 150.000. ~90% günstiger, bessere Qualität.

### 9.2 Style Guide

Beim ersten AI-Call wird aus dem vorhandenen Manuskript automatisch ein Style Guide extrahiert:

- Durchschnittliche Satzlänge und -struktur
- Adjektiv-Dichte
- Wie Szenen eröffnet werden
- Charakteristische Sprachmuster des Autors
- Genre-spezifische Ton-Eigenschaften

Der Autor kann den Style Guide manuell editieren und verfeinern. Er wird bei jedem Prosa-Pass mitgegeben.

### 9.3 Prosa-Pass Prompt-Strategie

Generalisierter System-Prompt der auf jedes Kapitel anwendbar ist:

- Dialoge bleiben exakt unverändert
- Emotionale Struktur und Dramaturgie bleiben vollständig erhalten
- Wiederholungen erkennen und eliminieren
- Holprige Übergänge glätten
- Satzrhythmus an emotionale Intensität anpassen
- Grammatik, Orthografie, Zeitinkonsistenzen korrigieren
- Logik-Inkonsistenzen elegant lösen
- Expositorische Einschübe rhythmisch besser integrieren

---

## 10. User Flows

### Flow A — Neues Buch anlegen
1. Buch erstellen: Titel, Sprache
2. `.docx` hochladen — Kapitel werden automatisch erkannt
3. Storylines definieren (oder Standard "Hauptlinie" übernehmen)
4. Akte definieren (optional)
5. Kapitel den Storylines und Akten zuordnen
6. AI Key hinterlegen (optional) → Analyse läuft im Hintergrund

### Flow B — Kapitel überarbeiten (AI)
1. Kapitel auswählen
2. "AI Prosa-Pass" starten
3. Überarbeitung streamt rein — Original und Revision nebeneinander
4. Diff-Ansicht: was wurde geändert und warum
5. Akzeptieren → wird neue aktive Version
6. Ablehnen → vorherige Version bleibt aktiv

### Flow C — Story Heartbeat Canvas
1. Canvas öffnen → Story Heartbeat mit vier Analyse-Spuren
2. Tension Arc zeigt Spannungskurve mit Akt-Blöcken und Plot Points
3. Chapter Hook Score zeigt sofort wo Kapitelenden schwach sind (farbige Blöcke)
4. Pacing Rhythm visualisiert Wortzahl-Variation über alle Kapitel
5. Storyline Weave zeigt POV-Verteilung und Storyline-Balance
6. Thriller Health Dashboard im rechten Panel: Score, schwächste Hooks, Next Action
7. Lanes einzeln ein-/ausblendbar, Stakes und Villain als Overlays

### Flow D — Analyse-Report
1. "Analyse starten" für ganzes Buch oder einzelnes Kapitel
2. Szenen-Audit läuft durch
3. Erster-Kapitel-Audit separat
4. Villain-Dashboard wird befüllt
5. Nächstes-Kapitel-Vorschlag wird generiert
6. Report exportierbar als `.md` oder `.txt`

### Flow E — Ohne AI
1. Buch anlegen, Kapitel importieren
2. Kapitel manuell bearbeiten
3. Versionierung funktioniert vollständig
4. Plot Points, Charaktere, Akte manuell pflegen
5. Canvas zeigt Struktur (ohne AI-Scoring)

---

## 11. Monetarisierung & Distribution

| Aspekt | Detail |
|---|---|
| **Modell** | Freemium — Basis kostenlos, AI-Features für €99 einmalig freischalten |
| **Basis** | Kostenlos, unbegrenzt nutzbar, alle manuellen Features |
| **AI Unlock** | €99 One-Time Purchase — schaltet alle AI-Features permanent frei |
| **AI-Kosten** | Trägt der Autor selbst via eigenem API Key (BYOK). Transparente Token-Kosten-Schätzung pro Operation in der UI. |
| **Spenden** | Optionaler "Support the Developer"-Button in der App |
| **Distribution** | Direkt-Download von eigener Website. Kein App Store. |
| **Lizenz** | Perpetual License — Offline-Aktivierung via License Key, kein Server-Check, funktioniert für immer |
| **Updates** | Kostenlose Updates innerhalb der Major-Version. Neue Genre-Profile in späteren Major-Versionen optional bezahlbar. |

---

## 12. Entwicklungsphasen

### Phase 1 — Foundation (MVP)
- NativePHP Setup + SQLite Schema
- `.docx` Import + Kapitel-Splitting
- Storyline, Akt, Charakter-Verwaltung (manuell)
- Kapitel-Editor mit Versionierung
- Plot Points manuell
- Einfacher Canvas ohne AI-Scoring

### Phase 2 — AI Layer
- API Key Verwaltung (verschlüsselt)
- Ingest + Embedding + sqlite-vec
- Style Guide Generierung
- AI Prosa-Pass mit Diff-Ansicht
- Charakter-Extraktion
- Pacing-Coach + Spannungskurve
- Szenen-Audit
- Thriller-Stil-Check
- Kapitelschluss-Analyse
- Erster-Kapitel-Audit
- Plothole-Detection
- Canvas mit AI-Scoring

### Phase 3 — Advanced
- Villain-Dashboard
- Stakes-Tracker
- Charakter-Konsistenz-Check
- Nächstes-Kapitel-Vorschlag
- Dichte-Analyse
- Backstory-Einstreuungs-Planer
- Cross-Storyline Konsistenz
- Export: ePub, PDF
- Beziehungs-Tracking Charaktere
- Analyse-Report Export

---

## 13. Entscheidungen & Produktdefinition

| Frage | Entscheidung |
|---|---|
| **Tool-UI Sprache** | Englisch — internationale Zielgruppe. `book_language` ist ein freies Feld, der Autor schreibt in beliebiger Sprache. |
| **Desktop vs. Web** | Konsequent Desktop-Only. Keine Web-Version geplant. |
| **Lizenzmodell** | Basis-App kostenlos — kein Purchase nötig. AI-Features (alle Features aus Abschnitt 6) werden durch einmalige Zahlung von **€99** freigeschaltet. Perpetual License, kein Server-Check, Offline-Aktivierung via License Key. |
| **Style Guide** | AI-generiert beim ersten Ingest, danach manuell editierbar. Der Autor hat immer das letzte Wort. |
| **Canvas** | Phase 1+2: reine Visualisierung. Drag & Drop und interaktive Bearbeitung in Phase 3. |
| **Genre-Profile** | Phase 1+2: Thriller only mit vollständigem Analyse-Set. Weitere Genre-Profile (Romance, Fantasy, Crime etc.) kommen in späteren Major-Versionen als erweiterbare Profile. |

### Lizenzmodell im Detail

```
Kostenlos (Basis)
├── Unbegrenzte Bücher
├── Kapitel-Import (.docx)
├── Manuelles Plot-, Charakter-, Akt-Management
├── Kapitel-Editor mit Versionierung
├── Plot Canvas (Visualisierung)
└── Export (.docx, .txt)

€99 One-Time (AI Unlock)
├── Alles aus Basis
├── API Key hinterlegen (BYOK — Anthropic oder OpenAI)
├── Ingest + Embedding + RAG
├── Style Guide Generierung + Edit
├── AI Prosa-Pass mit Diff
├── Alle Analyse-Module (Abschnitt 6)
└── Nächstes-Kapitel-Vorschlag
```

Der Autor trägt seine eigenen API-Kosten direkt. Die €99 sind die Lizenz für das Tool — nicht für AI-Tokens.

---

*Manuscript AI · Allgäu Digitalwerk · Internes Dokument*
