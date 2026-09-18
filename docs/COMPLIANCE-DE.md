# Gesetzliche Anforderungen und Abdeckung durch CopeX_WarrantyLabel

Stand: 2026-09-16 · Modulversion 0.1.0

Dieses Dokument ordnet jeder rechtlichen Anforderung zu, **wo und wie das Modul sie umsetzt** und **was offen bleibt**.
Es ist keine Rechtsberatung: Die Auslegung im Einzelfall und die Freigabe der gewählten Darstellungsvarianten liegen
beim Shopbetreiber bzw. dessen Rechtsberatung.

## 1. Rechtsgrundlagen und Fristen

| Quelle | Inhalt | Frist |
|---|---|---|
| Richtlinie (EU) 2024/825 (EmpCo) | ändert RL 2005/29/EG und 2011/83/EU: Pflicht zu harmonisiertem Hinweis und Label | umzusetzen bis 27.09.2026 |
| Durchführungsverordnung (EU) 2025/1960 | Gestaltung und Inhalt von Hinweis (Anhang I) und GARAN-Label (Anhang II); unmittelbar geltend | **anwendbar ab 27.09.2026** |
| EU-Kommission, „Practical guidelines for sellers and producers“, Ares(2026)4331985, April 2026 | Auslegungshilfe; im Folgenden **LL** mit Seitenzahl | — |
| Österreich: VRÄG 2026 (§ 4 Abs 1 Z 12, 12a FAGG; § 5a Abs 1 Z 5, 5a KSchG) | nationale Begleitregelung | 27.09.2026 |
| Deutschland: Art. 246, 246a EGBGB | nationale Begleitregelung | 27.09.2026 |
| European Accessibility Act (seit 06/2025) | Bedienbarkeit per Tastatur und Screenreader | bereits gültig |

Nicht verifiziert: Die nationalen Fundstellen stammen aus der Aufbereitung von CopeX
(https://copex.io/blog/magento-2-garan-label-gewaehrleistungslabel); EU-Quellen wurden im Original geprüft.

## 2. Gewährleistungshinweis (Pflicht für jede Ware an Verbraucher)

| # | Anforderung | Quelle | Umsetzung im Modul |
|---|---|---|---|
| N1 | Vollständiger, unveränderter Hinweis; keine Farb-/Abstands-/Schrift-/Zuschnitt-Änderung | LL S. 7–13 | Offizielle EU-Dateien unverändert ausgeliefert (`view/base/web/notice/{lang}.svg|.png`), Prüfsummen in `notice/CHECKSUMS`, Test `Test/Unit/Assets/NoticeAssetChecksumTest`; CSS setzt keine Filter/Opacity/Blend |
| N2 | „In prominenter Weise“ vor Vertragsschluss; geschachtelte Anzeige per Klick zulässig (LL), strengere Auslegung verlangt direkte Grafik | LL S. 16–19 | Modus je Platzierung: `copex_warrantylabel/notice_placement/{header,footer,category,search,cart,checkout,success,email}` = `off|direct|nested`; Default konservativ (direkt in Checkout/Success/E-Mail) |
| N3 | In Standard-Displaygröße lesbar | LL S. 15 | Mindestbreite `general/min_width_px` (Default 420 px); im Dialog proportionale Skalierung auf die Viewport-Breite, nie Zuschnitt |
| N4 | Klickbarer Link auf dasselbe Ziel wie der QR-Code, sprachabhängig | LL S. 15–16 | `Model/Language/LanguageRegistry` mit allen 24 Zielen (z. B. DE `europa.eu/youreurope/garantien`); Link steht neben jeder Grafik; alle 24 URLs live geprüft (HTTP 200) |
| N5 | Auch in der Bestellbestätigungs-E-Mail | LL S. 20 | `Plugin/Sales/EmailItemsPlugin` hängt Notice-PNG + Link an die Artikeltabelle an (Layout-Blöcke werden im E-Mail-Handle verworfen); Fehler werden abgefangen, die Mail bricht nie |
| N6 | Eine Fassung je Store View in der Sprache des Store View | Blog; LL S. 9 | `general/language` je Store View (Default aus Locale, sonst Englisch) — wichtig, weil Locale ≠ Inhaltssprache sein kann |
| N7 | Tastatur- und Screenreader-bedienbar | EAA; LL S. 33 | Echter `<button aria-haspopup="dialog" aria-controls>`, natives `<dialog>` mit `aria-label`, Fokus im Dialog, ESC schließt (Theme-Handler wird umgangen), Fokus kehrt zurück; Grafiken mit Alt-Text |
| N8 | Nur Waren; Nicht-Waren lösen keine Pflicht aus | RL 2011/83/EU | `general/excluded_product_types` (Default `virtual, downloadable, giftcard, mageworx_giftcards`); Checkout und E-Mail zeigen den Hinweis nur, wenn mindestens eine Ware enthalten ist |

## 3. GARAN-Label (nur bei qualifizierender Herstellergarantie)

| # | Anforderung | Quelle | Umsetzung im Modul |
|---|---|---|---|
| G1 | Nur bei Herstellergarantie: kostenlos, ganze Ware, Dauer > 2 Jahre | LL S. 2, 25 | Pflichtfelder je Variante; `Model/Garan/DurationParser` erzwingt > 2 und ≤ 99; ohne vollständige Daten kein Label. Ob die Garantie die Kriterien erfüllt, entscheidet die Datenpflege |
| G2 | Editierbar nur Marke, Modellkennung, Dauer; Dauer in ganzen oder halben Jahren mit Komma | LL S. 21–22 | Attribute `garan_brand`, `garan_model_identifier`, `garan_duration_years`, `garan_terms_url`; Parser akzeptiert „4,5“ und „4.5“, Vielfache von 0,5; Ausgabe immer mit Komma |
| G3 | Schrift Inter, Typografie und Abstände unverändert, QR ≥ 2 × 2 cm | LL S. 24–29 | PNG-Rendering mit Inter-TTF auf den offiziellen Hintergrund; Ankerpositionen als Konstanten in `Model/Garan/FieldFitChecker` (gegen LL S. 23/29 gemessen); zu breite Werte werden abgelehnt statt verkleinert |
| G4 | Am Produkt, vor dem Kauf sichtbar; Nested-Anzeige erlaubt, volles Label beim ersten Klick | LL S. 33–34 | Produktseite unter dem Preis: Nested-Label als Button → Dialog mit vollem Label; bei Varianten wechselt das Label mit der Auswahl, ohne Auswahl kein Label |
| G5 | Direkt vor der Bestellung im Checkout, am jeweiligen Artikel | LL S. 36 | Label am Artikel in der Zusammenfassung **und** zusätzlich als Liste in `before-place-order` direkt über dem Bestell-Button (wichtig auf Mobile, wo die Artikelliste eingeklappt ist) |
| G6 | In der Bestellbestätigungs-E-Mail | LL S. 37 | Pro sichtbarem Artikel Label-PNG, Alt-Text, EU-Info-Link und Link zu den Garantiebedingungen |
| G7 | Link auf das QR-Ziel des Labels | LL S. 21, 34 | `LanguageRegistry::GARAN_INFO_URL` = `https://europa.eu/youreurope/commercial-guarantee-durability/index.htm`, neben jedem Label |
| G8 | Werte hängen an der Variante (Modellkennung je Größe/Farbe) | Blog | Attribute `apply_to = simple`; Configurable löst über das gewählte Kind auf, Bundle über alle Kinder |
| G9 | Folgepflicht: Garantiebedingungen zugänglich **und auf dauerhaftem Datenträger spätestens bei Lieferung** | LL S. 2 Fn. 1; Art. 17 Abs. 2 RL (EU) 2019/771; § 9a Abs. 3 KSchG; § 479 Abs. 2 BGB; EuGH C-49/11 (Link genügt nicht) | Zwei Ebenen: (a) Pflichtfeld `garan_terms_url` je Store View für den sichtbaren Link am Label — ohne gültige http(s)-URL kein Label; (b) **PDF-Anhang an der Bestellbestätigung**, konfiguriert in den Modul-Optionen (`garan/attach_terms`, `garan/terms_file`, `garan/terms_filename`). Ein Observer auf `email_order_set_template_vars_before` prüft, ob die Bestellung GARAN-Positionen hat, ein Plugin auf `TransportBuilder::getTransport` hängt die Datei an die Symfony-Nachricht (ohne Zusatzmodul). Ein Satz in der Mail weist auf den Anhang hin. Ein Dokument je Store View genügt, solange es die abgedeckten Waren benennt |
| G10 | In Werbung nur für tatsächlich abgedeckte Produkte, nicht mehrdeutig | LL S. 39 | Kein Label ohne vollständige, gültige Daten; kein Label am Configurable-Parent und keines vor der Variantenwahl. Für Banner/Newsletter gibt es noch kein Widget (Release 3) |
| G11 | Nachweis zum Kaufzeitpunkt | Praxis | Snapshot als JSON an der Bestellposition (`sales_order_item.copex_garan_label`); spätere Attributänderungen ändern alte Bestellungen nicht |

## 4. Bekannte Grenzen und offene Punkte

| Thema | Status |
|---|---|
| Halbe Garantiejahre | Bei offizieller Schriftgröße passen nur ganze Jahre 3–99 und „7,5“ vor das Kalender-Icon. Andere Werte („2,5“, „4,5“ …) sind speicherbar, ergeben aber **kein Label** und den Audit-Grund `duration_does_not_fit`. Klärung bei der EU-Kommission (JUST-B1) offen |
| Datenqualität | Das Modul erzeugt keine Garantiedaten. Welche Produkte qualifizieren und wer Marke, Modell, Dauer und Bedingungen pflegt, ist Aufgabe des Shopbetreibers; `bin/magento copex:warranty-label:audit` listet unvollständige oder ungültige Datensätze |
| Einrichtung des Anhangs | Die PDF muss im Admin hochgeladen werden (Stores → Configuration → EU Guarantee Notice & GARAN Label → GARAN). `bin/magento config:set` schreibt bei `type="file"`-Feldern keinen Wert, weil das Backend-Model einen echten Upload erwartet. Der gespeicherte Wert ist scope-präfixiert — im Admin-Upload verifiziert: Default-Scope ergibt `default/datei.pdf` mit Ablage unter `pub/media/copex_warranty_label/terms/default/`, Store-View-Scope entsprechend `stores/<id>/datei.pdf`. Der Resolver akzeptiert zusätzlich einen reinen Dateinamen (z. B. aus einem per SQL gesetzten Wert) |
| Rechtliche Auslegung | Die Wahl „direkt“ oder „geschachtelt“ je Platzierung ist eine Rechtsfrage (LL lässt geschachtelt zu, die IT-Recht Kanzlei verlangt die direkte Grafik). Freigabe durch den Kunden bzw. dessen Rechtsberatung nötig |
| Marktplätze | Für Amazon, eBay, Kaufland, Mirakl u. a. liegt die Pflicht beim jeweiligen Interface; solche Store Views sollten das Modul deaktiviert haben |
| Rechnung, Lieferschein, weitere Mails | Nicht abgedeckt; LL verlangen nur die Bestellbestätigung |
| Englischsprachige Store Views mit deutscher Locale | Grafik, Link, Button- und Alt-Text sind konfigurierbar englisch; systemseitige Texte wie „Schließen“ und die GARAN-Beschriftungen folgen der Locale und erscheinen dort deutsch |
| Zahlungsarten ohne `before-place-order` | Hinweis und GARAN-Liste stehen im Checkout in Magentos Region `payments-list > before-place-order`, die jede Zahlungsart in ihrem eigenen Template über dem Bestell-Button ausgibt – dieselbe Stelle wie die AGB-Checkboxen. Eine Zahlungsart, deren Template die Region weglässt, zeigt weder AGB-Checkboxen noch Hinweis. **Je Zahlungsart prüfen:** Zahlungsart wählen, Hinweis muss über dem Button stehen. Fehlt er, ist das Template der Zahlungsart zu korrigieren oder die Komponente im Projekt-Theme an eine andere Stelle zu setzen (README, Abschnitt Themes) |
| Hyvä | Adapter vorbereitet, aber nicht Teil dieses Release |

## 5. Nachweise aus der Verifikation (lokal, 2026-09-15)

- Unit-Tests 293 (Modul) + 6 (Projekt-Patch), phpcs Magento2 ohne Fehler, `setup:upgrade` und `setup:di:compile` fehlerfrei.
- Browser (Headless Chrome, lokale Testinstanz): Produktseite mit Dialog (Tastatur, ESC, Fokusrückgabe), Variantenwechsel
  (10 Jahre → Label, 4,5 Jahre → keines), Checkout 1440 px und 375 px mit Hinweis und GARAN-Liste vor dem Bestell-Button.
- Testbestellung mit Bestätigungs-E-Mail (Notice-PNG + Link, zwei GARAN-Labels mit Links, alle Bilder HTTP 200).
- Alle 24 Your-Europe-Links live geprüft; Asset-Prüfsummen automatisiert getestet.
- Screenshots und Logs: `.omc/artifacts/warranty-label/` (nicht Teil des Moduls).

### Nachtrag Luma und Blank (lokal, 2026-09-18, Version 1.2.0)

- Anlass: In Luma erschienen Hinweis und GARAN-Liste im Checkout nicht (N2, G5), weil sie an einem Knoten hingen, den nur
  das ursprüngliche Projekt-Theme kannte. Seit 1.2.0 stehen beide in Magentos Region `payments-list > before-place-order`.
- Browser (Headless Chrome, Magento 2.4.8-p3, Themes Magento/luma und Magento/blank, je 1280 px und 375 px): Header,
  Footer, Kategorie, Suche, Warenkorb, Produktseite (einfach und konfigurierbar mit Swatches: 5 Jahre → Label,
  10 Jahre → anderes Label, Variante ohne Garantie → keines), Checkout mit zwei Zahlungsarten in den Modi direkt und
  geschachtelt (je Zahlungsart eigener Dialog, keine doppelten IDs), kein horizontaler Überlauf auf 375 px.
- Testbestellung in Luma: Erfolgsseite mit Hinweis und GARAN-Liste; Bestätigungs-E-Mail mit Notice-PNG, Link und
  GARAN-Labels.
- Unit-Tests 334, darunter `Test/Unit/Layout/CheckoutLayoutTest`, der jeden Eltern-Knoten der Checkout-Komponenten
  gegen das Layout von `Magento_Checkout` prüft; phpcs Magento2 ohne Fehler.
- Nicht geprüft: Hyvä, Zahlungsarten von Drittanbietern (siehe Abschnitt 4, „Zahlungsarten ohne `before-place-order`“).
