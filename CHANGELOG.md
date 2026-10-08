# Changelog

## 0.21.0 - 2026-09-28

- Read-only proefrun voor herhaalschema's met dezelfde item- en projectregelkeuze als de echte run, inclusief bedragen en foutmeldingen.
- Controlelijst voor factuurconcepten met herkomst, bedrag, editor- en PDF-links.
- Expliciete factuurmail en eenmalige handmatige herinnering toegevoegd met verzendhistorie en blokkade tegen dubbele/onzekere verzending.
- Concept-PDF gebruikt centraal de veilige moduletemplate; een projectoverride kan de niet-betalen-markering niet weglaten.

## 0.20.1 - 2026-09-28

- Conceptfacturen kunnen vanuit de editor als PDF worden bekeken zonder statuswijziging, nummerreservering of opslag van een afgeleid bestand.
- De standaard-PDF markeert een concept op de pagina en in de voettekst als niet-betaalbaar en verbergt betaalinstructies en IBAN.
- Onopgeslagen kopgegevens blokkeren de preview totdat de AJAX-opslag is gelukt; de definitieve PDF-route blijft concepten weigeren.

## 0.20.0 - 2026-09-28

- Optioneel projectverlengpunt voor terugkerende factuurregels toegevoegd, zonder projectafhankelijkheid in AdminInvoice.
- Een project kan bij conceptaanmaak het item, aantal en aanvullende regels bepalen; vaste schema's blijven ongewijzigd werken.

## 0.19.0 - 2026-09-28

- Tenantgebonden factuuritems toegevoegd met exacte decimale prijzen, eenheid en btw-keuze.
- Conceptfacturen kunnen een item overnemen; de regel bewaart de publieke bron-id en eigen prijs-/btw-snapshot.
- Herhaalschema's maken idempotente factuurconcepten per maand, kwartaal of jaar, met pauzeren en foutmelding.
- Beheerschermen leggen uit wat automatisch gebeurt en wanneer een medewerker het concept moet controleren.
- Optionele ontbrekende project-PDF-template-map verhindert de standaardmoduletemplate niet meer.

## 0.18.0 - 2026-09-21

- Optionele AdminReport-provider toegevoegd voor netto-omzet, ontvangen btw en recente factuur-/creditnotaboekingen.
- Gecrediteerde facturen blijven op hun oorspronkelijke documentdatum meetellen; creditnota’s verminderen omzet en btw op hun eigen datum.
- Alle bijdragen zijn tenant- en valutagebonden en blijven beperkt tot `Integration/Report`.

## 0.17.1 - 2026-09-21

- Dashboardbijdrage levert komende vervaldata van open facturen aan de gezamenlijke agenda.
- Snelle actie en achterstallige facturen hebben expliciete kleur- en statusmetadata.

## 0.17.0 - 2026-09-21

- Optionele Dashboard-provider toegevoegd voor openstaande facturen, maandelijkse netto-omzet, achterstanden, activiteit en snelle factuuraanmaak.
- Openstaande bedragen gebruiken Payment-allocaties wanneer de Payment-tabel aanwezig is en vallen anders veilig terug op de factuurstatus.
- De provider vult de Core-placeholders en de gedeelde factuurtabel met actuele tenantdata.

## 0.16.1 - 2026-09-21

- Los facturenpaneel verwijderd; de controller levert nu navigatiemetadata aan het gezamenlijke AdminCore-paneel.

## 0.16.0 - 2026-09-19

- Optionele `Integration/Quote/InvoiceConverter` toegevoegd voor geaccepteerde offertes.
- De adapter gebruikt het bestaande publieke CreateInvoice-pad en maakt uitsluitend een normale bewerkbare conceptfactuur.
- Factuurdatum komt van de systeemklok; de vervaldatum gebruikt de algemene instelling `billing_invoice_due_days` met veilige fallback.
- Herkomst wordt idempotent opgeslagen als `admin_quote + publieke offerte-id`, zonder cross-module foreign key of gedeelde tabel.
- Alleen de expliciete integratiemap importeert het consumer-owned Quote-contract; de Invoice-kern blijft Quote-onafhankelijk.
- Adapter-, modulegrens- en database-idempotentietests slagen; geen `force_aw` nodig.

## 0.15.0 - 2026-09-18

- Tenantgebonden read-query toegevoegd voor de recentste documenten op basis van de publieke Customer-id in de immutable klantsnapshot.
- Facturen en creditnota’s verschijnen samen in het facturatiepaneel op het klantdetail.
- De compacte gedeelde `TableRenderer` hergebruikt documentnummer-, status-, bedrag- en routepresentatie van het factuuroverzicht.
- De lijst is server-side begrensd tot vijf documenten en biedt directe links naar concepteditor, definitieve factuur of creditnota.
- Unit-, presenter-, template- en echte MariaDB-tests bewaken klantisolatie, tenantisolatie, volgorde en limiet.

## 0.14.0 - 2026-09-18

- Expliciete `Integration/Customer/CustomerDetailExtension` toegevoegd voor de actie `Nieuwe factuur` op het klantdetail.
- `/Flexgrid/AdminInvoice/create/{customerPublicId}` gebruikt de bestaande optionele provider om klant- en factuuradresvelden voor te vullen.
- Alleen actieve klanten met een volledig primair factuuradres krijgen de directe actie; ongeldige of verouderde routes vallen veilig terug op handmatige invoer met uitleg.
- De publieke Customer-id wordt in de conceptsnapshotbron bewaard; er is geen cross-module foreign key of live koppeling na finalisatie.
- Provider-, voorinvul-, escaping-, actiecontrast- en modulegrenstests toegevoegd.

## 0.13.1 - 2026-09-18

- CSS en JavaScript van de optionele klantselector worden expliciet op conceptfactuurschermen geregistreerd.
- De AJAX-zoeker is daardoor niet langer afhankelijk van impliciete template-assetdetectie of een actuele TemplateRegistry-cache.
- Outlineknoppen zijn in de administratieschermen vervangen door de zichtbare secundaire knopvariant.
- Regressietest toegevoegd voor de expliciete assetregistratie.

## 0.13.0 - 2026-09-18

- Publiek, Customer-onafhankelijk `InvoiceCustomerProviderInterface` en selectie-readmodel toegevoegd.
- Conventieloader ontdekt optioneel één Customer-provider zonder concrete AdminCustomer-import.
- AJAX-klantzoeker toegevoegd aan nieuwe en bestaande conceptfacturen.
- Selectie vult klant-, contact- en factuuradresvelden, terwijl handmatige invoer beschikbaar blijft.
- `PartySnapshot` versie 2 bewaart de publieke Customer-bron-id; versie 1 blijft leesbaar.
- Handmatige wijziging van gekopieerde gegevens verbreekt client-side de bronkoppeling.
- Vanilla-JS-, template-, provider-, snapshot- en echte PDO-regressietests toegevoegd.

## 0.12.0 - 2026-09-18

- Publieke, beperkte `InvoiceDetailContext` en `InvoiceDetailExtensionInterface` toegevoegd.
- Conventiegebaseerde loader toegevoegd voor optionele panelen van andere modules op het factuurdetail.
- De detailcontroller en template hebben een generieke extensieslot zonder concrete AdminPayment-import.
- Afzonderlijke readpoort toegevoegd waarmee een integratie na AJAX-mutatie de actuele factuur- en betaalstatus kan ophalen.
- Extensie-, ontdekking-, modulegrens- en PHP 7.3-regressietests toegevoegd.

## 0.11.0 - 2026-09-18

- Publiek, Payment-onafhankelijk `InvoicePaymentPortInterface` en `PayableInvoice`-read model toegevoegd.
- Tenantgebonden PDO-adapter toegevoegd die een definitieve factuur vergrendelt en uitsluitend de afgeleide betaalstatus projecteert.
- AdminInvoice importeert geen AdminPayment-code; de optionele koppeling blijft eigendom van de Payment-integratielaag.

## 0.10.0 - 2026-09-18

- Facturen en creditnota's worden nu tenantgebonden samengebracht in één gepagineerd documentoverzicht.
- De bestaande Flexgrid `TableRenderer` rendert beide documentsoorten met een herkenbare typekolom en directe detailroute.
- Documenttypefilter toegevoegd voor alle documenten, alleen facturen of alleen creditnota's; zoeken omvat ook creditnotanummer, reden en bronfactuurnummer.
- Creditnotarijen tonen hun negatieve bedrag en bronfactuur; vervaldatum is niet van toepassing.
- AJAX-filterreset, querystringbehoud en responsive toolbar zijn uitgebreid met het documenttype.
- De read-query begrenst beide `UNION ALL`-takken afzonderlijk op de actieve tenant en houdt sortering en paginering op de server.
- Unit-, template-, JavaScript- en echte MariaDB-integratietests dekken de gecombineerde lijst, filters, zoeken, routing en tenantisolatie.

## 0.9.0 - 2026-09-18

- Definitieve facturen hebben nu een bevestigde volledige-creditactie met verplichte reden via Flexgrids declaratieve AJAX-afhandeling.
- Een kleine vanilla-JS-class beheert uitsluitend de native dialoog; transport, CSRF, loader en redirect blijven bij Flexgrid.
- Tenantgebonden read-only creditnotadetail met bronfactuurlink toegevoegd.
- On-demand creditnota-PDF toegevoegd via dezelfde projecttemplatemap, branding en veilige templatefallback als facturen.
- De standaard PDF-template onderscheidt factuur en creditnota, toont de correctiereden en bevat voor creditnota's geen betalingsverzoek.
- Afzonderlijke algemene templatekeuze `administration_credit_note_pdf_template` toegevoegd, met terugval op de ingestelde factuurtemplate.
- UI-, JavaScript-, controller- en echte PDF-tests toegevoegd; schema- en PDO-integratietests slagen na de Autowire-scan.

## 0.8.0 - 2026-09-18

- Immutable `CreditNote`-aggregate en volledige-creditnota-use-case toegevoegd.
- Creditnota's gebruiken een eigen `credit_note`-nummerreeks en standaardformaat `CN-YYYY-0001`.
- Oorspronkelijke regels, klant-/adressnapshot en meerdere btw-groepen worden exact negatief gespiegeld.
- Opslagrecords en tenantgebonden PDO-adapter voor creditnota's en creditnotaregels toegevoegd.
- Creditnota-opslag en de statusovergang van de bronfactuur naar `credited` delen één transactie.
- Frameworkvrije domein-/use-casetests, Autowire-metadatatests en voorbereide schema-/PDO-integratiedekking toegevoegd.

## 0.7.0 - 2026-09-18

- On-demand PDF-route en downloadknop toegevoegd voor definitieve, tenantgebonden facturen; er wordt geen PDF-bestand persistent opgeslagen.
- Scalar `InvoicePdfViewModelFactory` en `GenerateInvoicePdf`-use-case toegevoegd bovenop uitsluitend het AdminPdf-renderercontract.
- Projecttemplates uit `Templates/AdminInvoice/Pdf` krijgen prioriteit boven de meegeleverde veilige `default.php`-fallback.
- Algemene instelling `administration_invoice_pdf_template` en automatisch ontdekkende templatekeuze toegevoegd.
- Bedrijfsgegevens en lokaal logo komen uit de algemene settings; primaire en secundaire merkkleur komen uit de project-`Variables.scss`, met Flexgrid- en vaste fallbacks.
- Default A4-factuurtemplate, pad-/viewmodeltests en een echte visueel gecontroleerde Dompdf-fixture toegevoegd.

## 0.6.0 - 2026-09-18

- Transactionele `FinalizeInvoice`-use-case toegevoegd met `FOR UPDATE`, tenant-/jaarreeks en standaardnummerformaat `YYYY-0001`.
- Ruwe sequencewaarde en definitieve totalen worden samen met nummer, status en finalisatietijd atomair opgeslagen.
- AJAX-finalisatieknop toegevoegd met bevestiging, minimaal-één-regelcontrole en blokkering bij onopgeslagen kopgegevens.
- Read-only detailroute en detailweergave toegevoegd; definitieve overzichtsregels linken hiernaartoe.
- Unit-, presenter-, template-, SCSS-, metadata- en MariaDB-integratiedekking uitgebreid voor finalisatie en immutable weergave.

## 0.5.2 - 2026-09-18

- Herbruikbare `admin-form`-basis toegevoegd die de globale Flexgrid-formuliergrid voor nieuwe Admin UI-formulieren neutraliseert.
- Aanmaak-, editor-, regel- en overzichtsformulieren gebruiken de nieuwe basisclass; hun eigen gridtemplates bepalen daardoor weer de kolomindeling.
- Layoutregressietest en documentatie voor gebruik met `admin-form-grid` of een modulespecifieke grid toegevoegd.

## 0.5.1 - 2026-09-18

- De editroute leest de factuur-public-id nu uit het argument-array dat Flexgrid aan backendcontrollers doorgeeft.
- Een regressietest toegevoegd voor openen via `/Flexgrid/AdminInvoice/edit/{publicId}`.

## 0.5.0 - 2026-09-18

- Bestaande conceptregels inline wijzigbaar gemaakt voor omschrijving, hoeveelheid, eenheid, prijs en btw-keuze.
- `UpdateInvoiceLineCommand` en tenantgebonden, transactionele `UpdateInvoiceLine`-use-case toegevoegd.
- Vanilla `AdminInvoiceEditor`-class toegevoegd die bij `change` uitsluitend het gekoppelde declaratieve AJAX-formulier submit.
- Regel-public-id, positie en oorspronkelijke `created_at` blijven behouden; totalen worden server-side opnieuw berekend.
- Toekomstige optionele Customer-integratie vastgelegd zonder foreign key of harde Invoice-afhankelijkheid.
- Unit-, template-, JavaScript- en echte MariaDB-tests voor inline regelwijzigingen toegevoegd.

## 0.4.0 - 2026-09-18

- Geldige conceptaanmaak met handmatige klant-, adres- en factuurbasisgegevens toegevoegd.
- Tenantgebonden concepteditor toegevoegd voor headergegevens, klantsnapshot en factuuradres.
- `GetInvoice`, `UpdateDraftInvoice`, `AddInvoiceLine` en `RemoveInvoiceLine` application use-cases toegevoegd.
- Aggregate-save met transactionele `FOR UPDATE`-vergrendeling, server-side totalen en behoud van regel-`created_at` toegevoegd.
- Regels toevoegen en verwijderen verversen regels en totalen via bestaande `AjaxEvent`/`AjaxResponse`-containers zonder module-JavaScript.
- Gedeelde Admin UI uitgebreid met herbruikbare form-grid-, field- en actionclasses.
- Unit-, template- en echte MariaDB-integratietests voor de editor en aggregate-save toegevoegd.

## 0.3.1 - 2026-09-18

- Overzichtinteractie herschreven naar een vanilla JavaScript-class zonder directe jQuery-afhankelijkheid.
- Het bestaande declaratieve `ajax="true"`-formulier blijft via `AjaxEvent`, de centrale Flexgrid `Ajax` dispatcher en `AjaxResponse` lopen.
- Regressietest toegevoegd die eigen `fetch`/`XMLHttpRequest`-transport en jQuery-gebruik in de Invoice-module uitsluit.

## 0.3.0 - 2026-09-18

- `ListInvoices` read model, queryvalidatie en tenantgebonden PDO-read repository toegevoegd.
- Zoeken, status- en betaalstatusfilters, jaarfilter, whitelist-sortering en paginering toegevoegd.
- Valuta-afzonderlijke overzichtssamenvattingen voor aantallen, omzet, betaald en openstaand toegevoegd.
- Eerste Flexgrid Invoice-controller, backendpanel en AJAX-overzicht toegevoegd.
- Generieke `TableRenderer` gebruikt voor alle factuurrijen; templates voeren geen queries of berekeningen uit.
- UI-, template-, JavaScript-, SCSS- en echte database-integratietests toegevoegd.

## 0.2.0 - 2026-09-18

- Werkelijk door Autowire aangemaakte Invoice-tabellen, kolomtypen, nullability en indexes gecontroleerd.
- Tenantgebonden `InvoiceRepositoryInterface` en PDO-adapter toegevoegd.
- `CreateInvoiceCommand` en transactionele, idempotente `CreateInvoice`-use-case toegevoegd.
- Ontbrekende optionele unique waarden worden als SQL `NULL` opgeslagen.
- Persistence-hydration van snapshots, regels, bedragen, statussen en definitieve totalen toegevoegd.
- Database-integratietest toegevoegd die volledig wordt teruggerold en tenantisolatie bewijst.

## 0.1.0 - 2026-09-18

- Eerste frameworkvrije Invoice-domain slice toegevoegd.
- Exacte schaal-4 hoeveelheden, regelafronding, meerdere btw-tarieven en negatieve regels geïmplementeerd.
- Versioned klant- en adressnapshots toegevoegd.
- Gescheiden factuur- en betaalstatussen en immutable finalisatie toegevoegd.
- Autowire-records met tenantgebonden unique/composite indexes toegevoegd.
- PHP 7.3 unit- en persistencemetadatatests toegevoegd.
