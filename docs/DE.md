# Warranty Label
# Extension for Magento 2
## Bedienungsanleitung

CopeX GmbH
Web: https://copex.io
Email: office@copex.io

---

## Inhaltsverzeichnis

1. [Einleitung](#1-einleitung)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Konfiguration](#3-konfiguration)
4. [Produktdaten des GARAN-Labels](#4-produktdaten-des-garan-labels)
5. [Garantiebedingungen als Anhang](#5-garantiebedingungen-als-anhang)
6. [Datenkontrolle](#6-datenkontrolle)
7. [Fehlerbehandlung](#7-fehlerbehandlung)
8. [Pflichten, die beim Betreiber bleiben](#8-pflichten-die-beim-betreiber-bleiben)

---

## 1 Einleitung

Ab dem **27. September 2026** verlangt die Durchführungsverordnung **(EU) 2025/1960** in Verbindung mit der Richtlinie **(EU) 2024/825** zwei neue Pflichtangaben im Online-Handel. Dieses Modul setzt beide um:

- **Der Gewährleistungshinweis** (Anhang I der Verordnung) ist eine offizielle Grafik, die jeder Shop zeigen muss, der Waren an Verbraucher verkauft. Sie informiert über die gesetzliche Gewährleistung und ist unabhängig davon verpflichtend, ob ein Hersteller zusätzlich eine Garantie gibt.
- **Das GARAN-Label** (Anhang II) betrifft nur einzelne Produkte: solche, für die der Hersteller eine kostenlose Haltbarkeitsgarantie für die gesamte Ware und für mehr als zwei Jahre gewährt.

Beide Grafiken sind amtlich vorgegeben. Das Modul liefert sie in allen 24 EU-Sprachen mit und verändert sie nie — weder Farben noch Schrift, Abstände oder QR-Code. Beim GARAN-Label werden ausschließlich die vier vorgesehenen Felder gefüllt: Marke, Modellkennung, Dauer und der Link auf die Garantiebedingungen.

Das Modul ist nach der Installation **ausgeschaltet**. Nichts erscheint im Shop, bevor Sie es bewusst aktivieren.

> Dieses Handbuch beschreibt die Bedienung. Es ersetzt keine Rechtsberatung. Welche Ihrer Produkte eine qualifizierende Herstellergarantie haben und wie die Anzeige rechtlich zu bewerten ist, entscheiden Sie beziehungsweise Ihre Rechtsberatung.

---

## 2 Voraussetzungen

| Komponente | Version |
|---|---|
| Magento 2 Open Source / Adobe Commerce | 2.4.x |
| PHP | ab 8.2 |
| PHP-Erweiterung GD | mit FreeType-Unterstützung |

Die GD-Erweiterung **muss mit FreeType übersetzt sein**. Das GARAN-Label wird serverseitig als Bild erzeugt, indem die Textfelder in die amtliche Vorlage gesetzt werden. Ohne FreeType kann das Modul keine Schrift zeichnen und erzeugt kein Label. Ob Ihre Installation geeignet ist, zeigt `php -i | grep -i freetype`.

Der Gewährleistungshinweis funktioniert auch ohne GD, weil er als fertige Grafik ausgeliefert wird.

### 2.1 Installation

```bash
composer require copex/module-warranty-label
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

### 2.2 Deinstallation

```bash
bin/magento module:uninstall CopeX_WarrantyLabel --remove-data
```

**Der Zusatz `--remove-data` ist entscheidend.** Ohne ihn bleiben die vier GARAN-Attribute in der Datenbank
stehen, und deren hinterlegtes Backend-Modell verweist auf Klassen dieses Moduls. Eine Installation, die die
Dateien verliert, die Attributzeilen aber behält, beantwortet danach **jede Produktseite mit einem Fehler** —
denn jedes Laden von Produktattributen versucht, die nicht mehr vorhandene Klasse zu erzeugen.

Entfernt werden dabei: die vier Attribute samt ihrer Attributgruppe, alle Einstellungen unterhalb von
`copex_warrantylabel`, die Spalte `sales_order_item.copex_garan_label` und die zwischengespeicherten
Label-Grafiken unter `pub/media/copex_warranty_label/garan`.

**Nicht entfernt wird die hochgeladene PDF** mit den Garantiebedingungen unter
`pub/media/copex_warranty_label/terms`. Das ist Ihr Dokument, möglicherweise noch aus alten Bestellungen
verlinkt; löschen Sie es von Hand, wenn Sie es nicht mehr benötigen.

---

## 3 Konfiguration

Alle Einstellungen liegen unter *Stores → Configuration → Sales → EU Guarantee Notice & GARAN Label*. Der Bereich gilt **pro Store View**, sodass jede Storefront eine eigene Sprache und eigene Anzeigemodi haben kann.

![Der Konfigurationsbereich mit allen drei Gruppen](screenshots/01_config_copex_warrantylabel.png)

### 3.1 Allgemeine Einstellungen

- **Enable Module** — der Hauptschalter. Steht er auf *No*, erscheint in diesem Geltungsbereich weder ein Hinweis noch ein Label, unabhängig von allen anderen Einstellungen. *Standard: No.*
- **Label Language** — wählt die amtliche Sprachfassung des Hinweises und das Ziel des Links. *Automatic (from store locale)* leitet die Sprache aus der Store-Locale ab. Setzen Sie den Wert ausdrücklich, wenn die Locale nicht der Sprache Ihrer Shop-Inhalte entspricht — etwa bei einer englischsprachigen Storefront, die technisch auf `de_AT` läuft. Lässt sich keine Sprache bestimmen, greift Englisch.
- **Nested Display Button Text** — die Beschriftung des Buttons, der den Hinweis in der geschachtelten Anzeige öffnet. Leer lassen für den übersetzten Standardtext „Ihre gesetzlichen Gewährleistungsrechte".
- **Notice Alternative Text** — der Alternativtext der Grafik für Screenreader und für E-Mail-Programme, die Bilder blockieren. Leer lassen für den übersetzten Standardtext.
- **Minimum Notice Width (CSS px)** — die Grafik wird nie schmaler dargestellt als dieser Wert, damit ihr QR-Code scanbar bleibt. *Standard: 420.* Verkleinern Sie ihn nicht ohne Not: Bei geringerer Breite unterschreitet der QR-Code die geforderte Mindestgröße.
- **Product Types Without Legal Guarantee Notice** — Produkttypen, die keine Waren im Sinne der Gewährleistung sind, etwa Gutscheine oder Downloads. *Standard: Virtual, Downloadable, Gift Card, MageWorx Gift Cards.* Im Warenkorb, im Checkout und in der E-Mail erscheint der Hinweis nur, wenn mindestens ein anderer Artikel enthalten ist.

### 3.2 Platzierungen des Gewährleistungshinweises

Für jede Platzierung wählen Sie einen von drei Modi:

| Modus | Bedeutung |
|---|---|
| **Off** | Keine Ausgabe an dieser Stelle. |
| **Direct (complete graphic)** | Die vollständige amtliche Grafik steht unmittelbar auf der Seite. |
| **Nested (button opens dialog)** | Ein Button öffnet die Grafik beim ersten Klick in einem Dialogfenster. |

Die geschachtelte Anzeige ist nach den Leitlinien der EU-Kommission zulässig. Eine strengere Auslegung verlangt die unmittelbare Darstellung. Weil das eine Rechtsfrage ist, lässt das Modul die Entscheidung für jede Platzierung einzeln zu.

| Platzierung | Standard |
|---|---|
| Header | Nested |
| Footer | Nested |
| Category Page | Nested |
| Search Results | Nested |
| Shopping Cart | Nested |
| Checkout (before Place Order) | Direct |
| Checkout Success Page | Direct |
| Order Confirmation Email | Direct |

Zwei Hinweise zur Wahl des Modus:

- **Enge Container brauchen „Nested".** Ist der verfügbare Platz schmaler als die eingestellte Mindestbreite, wirkt die direkte Grafik abgeschnitten. Die Checkout-Seitenleiste vieler Themes ist schmaler. Im Dialog erscheint die Grafik dagegen in voller Größe.
- **In E-Mails gibt es keine Dialoge.** Steht die E-Mail-Platzierung auf *Nested*, wird sie wie *Direct* ausgegeben.

Der Dialog ist vollständig mit der Tastatur bedienbar: Enter oder Leertaste öffnen ihn, Escape schließt ihn, und der Fokus kehrt anschließend auf den Button zurück.

![Im geschachtelten Modus öffnet ein Button den Hinweis](screenshots/05_notice_trigger.png)

![Der Dialog zeigt die vollständige amtliche Grafik](screenshots/06_notice_dialog.png)

### 3.3 GARAN-Label

- **Enable GARAN Label** — schaltet das Label frei. *Standard: No.* Solange es ausgeschaltet ist, bleiben die Produktattribute erhalten, werden aber nirgends angezeigt.
- **Product Page** — *Standard: Nested.*
- **Checkout (before Place Order)** — *Standard: Direct.* Das Label erscheint sowohl am jeweiligen Artikel als auch gesammelt direkt über dem Bestell-Button. Die zweite Stelle ist auf Mobilgeräten wichtig, wo die Artikelliste eingeklappt ist.
- **Checkout Success Page** — *Standard: Direct.*
- **Order Confirmation Email** — *Standard: Direct.*

Die übrigen Felder der Gruppe betreffen den Anhang und sind in Kapitel 5 beschrieben.

![Der Gewährleistungshinweis im Checkout, hier geschachtelt](screenshots/08_checkout_notice.png)

![Das GARAN-Label im Checkout, direkt über dem Bestell-Button](screenshots/07_checkout_garan_summary.png)

---

## 4 Produktdaten des GARAN-Labels

Das Modul erzeugt keine Garantiedaten. Es zeigt nur an, was Sie pflegen.

Bei der Installation entsteht in jedem Attributset die Gruppe **EU GARAN Guarantee** mit vier Attributen. Sie gelten **nur für einfache Produkte**, denn die Modellkennung gehört zur konkreten Variante und nicht zum konfigurierbaren Elternprodukt.

| Attribut | Gültigkeitsbereich | Bedeutung |
|---|---|---|
| **GARAN Brand** | global | Die Marke, wie sie auf dem Label steht. |
| **GARAN Model Identifier** | global | Die Modellkennung, wie sie auf dem Label steht. |
| **GARAN Guarantee Duration (Years)** | global | Ganze oder halbe Jahre, mehr als 2, etwa `3` oder `4,5`. Leer lassen, wenn es keine kostenlose Herstellergarantie auf die gesamte Ware gibt. |
| **GARAN Guarantee Terms URL** | Store View | Vollständige `http://`- oder `https://`-Adresse der Garantiebedingungen in der Sprache der Storefront. |

Ein Label erscheint nur, wenn **alle vier Werte vorhanden und gültig** sind. Fehlt eines, zeigt das Modul nichts an — es zeigt niemals ein unvollständiges Label.

![Die vier GARAN-Attribute am einfachen Produkt](screenshots/02_product_garan_attributes.png)

![Auf der Produktseite öffnet ein Button das Label](screenshots/03_product_garan_trigger.png)

![Das vollständige GARAN-Label im Dialog](screenshots/04_product_garan_label.png)

### 4.1 Wann ein Produkt überhaupt in Frage kommt

Das GARAN-Label ist kein Werbemittel, sondern eine Pflichtangabe für einen eng umrissenen Fall. Die Garantie muss

- vom **Hersteller** stammen (nicht vom Händler),
- für den Verbraucher **kostenlos** sein,
- die **gesamte Ware** abdecken (nicht nur einzelne Bauteile) und
- **länger als zwei Jahre** laufen.

Trifft auch nur eines davon nicht zu, darf kein Label gesetzt werden. Lassen Sie die Dauer dann leer.

### 4.2 Varianten

Bei konfigurierbaren Produkten hängen die Werte an der gewählten Variante. Das Modul zeigt deshalb

- **kein** Label am konfigurierbaren Elternprodukt,
- **kein** Label, solange noch keine Variante gewählt ist,
- und wechselt das Label, sobald der Kunde eine andere Variante wählt.

Bei Bundle-Produkten werden alle enthaltenen Artikel berücksichtigt.

### 4.3 Halbe Garantiejahre

Die Verordnung erlaubt halbe Jahre. In der amtlichen Schriftgröße passen jedoch nur ganze Jahre von 3 bis 99 sowie der Wert `7,5` in das dafür vorgesehene Feld. Andere Halbjahreswerte wie `2,5` oder `4,5` können Sie speichern, es entsteht aber **kein Label**, und die Datenprüfung nennt den Grund `duration_does_not_fit`.

Das ist Absicht. Die Alternative wäre, die Schrift zu verkleinern oder das Kalendersymbol zu verschieben — beides verstößt gegen die Gestaltungsvorgaben. Eine Klärung bei der EU-Kommission ist angestoßen.

### 4.4 Bestellte Ware behält ihr Label

Beim Abschluss einer Bestellung speichert das Modul die Labeldaten an der Bestellposition. Ändern Sie später ein Attribut, ändert das **alte Bestellungen nicht**. Ein erneut versandter Beleg zeigt weiterhin die Angaben, die zum Kaufzeitpunkt galten.

---

## 5 Garantiebedingungen als Anhang

Ein Link auf eine Webseite genügt rechtlich nicht. Die Garantieerklärung muss den Verbraucher **auf einem dauerhaften Datenträger** erreichen, spätestens bei der Lieferung (Art. 17 Abs. 2 der Richtlinie (EU) 2019/771, § 9a Abs. 3 KSchG, § 479 Abs. 2 BGB). Der Europäische Gerichtshof hat entschieden, dass eine Webseite, auf die nur verwiesen wird, diese Anforderung nicht erfüllt (Rechtssache C-49/11).

Das Modul hängt deshalb eine PDF-Datei an die Bestellbestätigung — und zwar nur bei Bestellungen, die mindestens ein Produkt mit GARAN-Label enthalten. Ein Zusatzmodul ist dafür nicht nötig.

### 5.1 Einrichtung

- **Attach Guarantee Terms to the Order Confirmation** — schaltet den Anhang ein. *Standard: No.*
- **Guarantee Terms File (PDF)** — die Datei. Ein Dokument je Store View.
- **Attachment File Name** — der Name, den der Kunde in der E-Mail sieht. Leer lassen für den Namen der hochgeladenen Datei. *Standard: `Garantiebedingungen.pdf`.*

**Die Datei muss im Admin hochgeladen werden.** Der Befehl `bin/magento config:set` kann Datei-Felder nicht beschreiben, weil im Hintergrund ein echter Upload erwartet wird. Ein per Kommandozeile gesetzter Wert bleibt wirkungslos.

![Die Bestellbestätigung mit Label, Links und dem Hinweis auf den Anhang](screenshots/09_email_garan_section.png)

### 5.2 Ein Dokument für mehrere Produkte

Ein Dokument je Store View genügt, solange darin steht, für welche Waren es gilt — etwa „gilt für alle Produkte der Marke X mit GARAN-Label". Benötigen Sie unterschiedliche Bedingungen für unterschiedliche Marken, fassen Sie diese in einem Dokument zusammen oder trennen Sie die Marken auf eigene Store Views.

Der Anhang ersetzt nicht den Link am Label. Beide sind vorgeschrieben: der Link für die Information vor dem Kauf, der Anhang für den dauerhaften Datenträger.

---

## 6 Datenkontrolle

Der folgende Befehl listet alle einfachen Produkte, deren GARAN-Daten unvollständig oder ungültig sind und die deshalb kein Label zeigen:

```bash
bin/magento copex:warranty-label:audit
```

| Option | Bedeutung |
|---|---|
| `--store=<ID oder Code>` | Prüft die Werte in diesem Store View. Ohne Angabe werden die Admin-Werte geprüft. |
| `--limit=<Anzahl>` | Bricht nach dieser Zahl beanstandeter Produkte ab. `0` bedeutet kein Limit. |

Die Ausgabe ist eine Tabelle mit SKU, Produkt-ID und den Gründen, gefolgt von einer Zusammenfassung, wie viele Produkte geprüft wurden.

Weil die Adresse der Garantiebedingungen pro Store View gilt, lohnt ein Lauf je Storefront:

```bash
bin/magento copex:warranty-label:audit --store=at
```

Führen Sie den Befehl nach jedem Massenimport aus. Werkzeuge, die direkt in die Datenbank schreiben, umgehen die Prüfung beim Speichern — das Modul prüft deshalb zusätzlich bei der Anzeige, sodass fehlerhafte Daten nie zu einem falschen Label führen, aber eben auch stillschweigend zu gar keinem.

---

## 7 Fehlerbehandlung

### 7.1 Es erscheint kein GARAN-Label

Die Datenprüfung aus Kapitel 6 nennt für jedes Produkt einen Grund:

| Grund | Bedeutung |
|---|---|
| `missing_brand` | Die Marke fehlt. |
| `missing_model_identifier` | Die Modellkennung fehlt. |
| `missing_duration` | Die Dauer fehlt. |
| `missing_terms_url` | Der Link auf die Garantiebedingungen fehlt. |
| `invalid_duration` | Die Dauer ist keine Zahl über 2 in Schritten von 0,5, oder sie liegt über 99. |
| `duration_does_not_fit` | Die Dauer ist zulässig, passt aber nicht in das Feld des Labels (siehe Abschnitt 4.3). |
| `invalid_terms_url` | Der Link ist keine vollständige `http://`- oder `https://`-Adresse. |
| `too_long` | Marke oder Modellkennung sind zu breit für das vorgesehene Feld. |

Meldet die Prüfung nichts und es erscheint trotzdem kein Label, prüfen Sie der Reihe nach: Ist *Enable Module* aktiv? Ist *Enable GARAN Label* aktiv? Steht die Platzierung nicht auf *Off*? Handelt es sich um ein einfaches Produkt beziehungsweise wurde eine Variante gewählt?

### 7.2 Marke oder Modellkennung werden abgelehnt

Die Felder des Labels haben eine feste Breite. Das Modul misst den Text in der amtlichen Schrift und lehnt zu breite Werte ab, statt die Schrift zu verkleinern. Kürzen Sie die Angabe — meist genügt es, Zusätze wie die Produktlinie wegzulassen.

### 7.3 Der Hinweis wirkt abgeschnitten

Der Container ist schmaler als die eingestellte Mindestbreite. Stellen Sie diese Platzierung auf *Nested*; im Dialog erscheint die Grafik in voller Größe. Verringern Sie nicht die Mindestbreite, sonst wird der QR-Code zu klein.

### 7.4 Die E-Mail enthält keinen Anhang

Der Anhang wird nur bei Bestellungen mitgeschickt, die mindestens ein Produkt mit GARAN-Label enthalten. Prüfen Sie außerdem, ob *Attach Guarantee Terms* im richtigen Store View aktiv ist und ob dort tatsächlich eine Datei hinterlegt wurde — ein über die Kommandozeile gesetzter Wert bleibt wirkungslos (siehe Abschnitt 5.1).

### 7.5 Die Sprache passt nicht zum Shop

Die Sprache des Hinweises folgt der Einstellung *Label Language*, nicht der Locale. Steht sie auf *Automatic*, wird die Locale des Store Views herangezogen. Bei einer englischsprachigen Storefront auf deutscher Locale setzen Sie die Sprache ausdrücklich.

---

## 8 Pflichten, die beim Betreiber bleiben

Das Modul stellt die vorgeschriebenen Angaben dar. Es beurteilt nicht, ob sie zutreffen. In Ihrer Verantwortung bleiben:

1. **Die Auswahl der Produkte.** Welche Ware eine qualifizierende Herstellergarantie trägt, entscheiden Sie anhand der Herstellerzusage.
2. **Die Pflege der Daten.** Marke, Modellkennung, Dauer und Bedingungen müssen aktuell sein. Läuft eine Garantiezusage aus, entfernen Sie die Werte.
3. **Der Inhalt der Garantiebedingungen.** Die hinterlegte PDF-Datei ist Ihr Dokument; das Modul prüft weder Inhalt noch Vollständigkeit.
4. **Die Wahl zwischen direkter und geschachtelter Anzeige.** Das ist eine Rechtsfrage, die Sie mit Ihrer Rechtsberatung klären sollten.
5. **Marktplätze.** Auf Amazon, eBay und vergleichbaren Kanälen liegt die Darstellungspflicht beim jeweiligen Marktplatz. Für Store Views, die solche Kanäle bedienen, schalten Sie das Modul aus.

Eine vollständige Gegenüberstellung der rechtlichen Anforderungen und ihrer Umsetzung liegt dem Modul als Datei `docs/COMPLIANCE-DE.md` bei. Dieses Dokument richtet sich an Ihre Rechtsberatung.
