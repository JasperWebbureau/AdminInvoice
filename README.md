# AdminInvoice

Versie: `0.21.0`

Factuurverwerking: nieuwe herhaalfacturen worden tenantgebonden ingepland. Conceptcontrole blijft standaard handmatig; de instelling op `AdminInvoiceProcessing/index` kan alleen nieuwe concepten automatisch goedkeuren. De dagtaak verstuurt definitieve maandfacturen en roept een optionele `InvoiceCollectionExtensionInterface` aan. AdminInvoice kent geen concrete betaalprovider. Installeer `admin_invoice_processing` via de gecontroleerde Autowire-scan. De generieke worker is `bin/process-invoices.php`; de optionele Mollie-provider wordt beschreven in [AdminPayment](../AdminPayment/DIRECT-DEBIT.md).

Native Flexgrid-module voor tenantgebonden facturen. De kern gebruikt AdminCore, maar heeft geen afhankelijkheid op Customer, Payment, Quote, Webshop of de oude administratie; optionele imports staan uitsluitend in expliciete `Integration/*`-adapters.

## Huidige inhoud

- `Domain\Model\Invoice` bewaakt conceptmutaties, finalisatie en immutable definitieve facturen.
- `Domain\Model\InvoiceLine` rekent uitsluitend met schaal-4 hoeveelheden en `Money` in minor units.
- `InvoiceCalculator` rondt netto en btw per regel half-up af en sommeert daarna de document- en btw-groeptotalen.
- `PartySnapshot` en `AddressSnapshot` zijn versioned en maken de factuur onafhankelijk van een Customer-module.
- Een optionele `InvoiceCustomerProviderInterface` laat een geïnstalleerde Customer-module zoekresultaten en snapshotdata leveren zonder concrete import in Invoice.
- Nieuwe en bestaande conceptfacturen kunnen via een declaratieve AJAX-zoeker een bestaande klant kiezen; handmatige invoer blijft beschikbaar.
- De expliciete `Integration/Customer`-laag voegt op het klantdetail een actie toe waarmee `/create/{customerPublicId}` direct met providerdata wordt vooringevuld.
- Hetzelfde klantdetailpaneel toont de vijf recentste facturen en creditnota’s via de gedeelde compacte `TableRenderer`, met directe links naar editor of read-only detail.
- `PartySnapshot` versie 2 bewaart de publieke Customer-bron-id. Versie-1-snapshots blijven achterwaarts compatibel.
- Factuurstatus en betaalstatus zijn afzonderlijke PHP 7.3-compatible value objects.
- Het publieke `InvoicePaymentPortInterface` laat een optionele integratie betaalstatus projecteren zonder dat Invoice AdminPayment importeert.
- Het factuurdetail ontdekt optionele `InvoiceDetailExtensionInterface`-implementaties en deelt alleen een beperkte immutable context; concrete uitbreidingsmodules worden niet geïmporteerd.
- De Autowire-records `InvoiceRecord` en `InvoiceLineRecord` definiëren de databasevorm met tenantgebonden indexes.
- `CreateInvoice` bouwt een concept vanuit scalar input, gebruikt uitsluitend de actieve `TenantContext` en slaat factuur plus regels binnen één transactie op.
- `PdoInvoiceRepository` biedt alleen tenantgebonden lookups en hydrateert snapshots, bedragen en regels terug naar het domeinmodel.
- `source + external_id` maakt retries vanuit webshops en importers idempotent.
- De optionele `Integration/Quote/InvoiceConverter` zet een geaccepteerde offerte-overdracht om naar een normaal concept en gebruikt `admin_quote + quotePublicId` voor idempotentie.
- `ListInvoices` gebruikt een licht read model met tenantgebonden zoeken, statusfilters, jaarfilter, whitelist-sortering en paginering.
- `/Flexgrid/AdminInvoice/invoices` toont een AJAX-first documentoverzicht met facturen én creditnota's, kengetallen, filters en de gedeelde Flexgrid `TableRenderer`.
- De overzichtinteractie is een vanilla JavaScript-class; transport, loader, CSRF en responseafhandeling blijven bij de centrale Flexgrid AJAX-laag.
- `/Flexgrid/AdminInvoice/create` maakt pas na geldige klant-, adres- en factuurbasisgegevens een concept aan.
- `/Flexgrid/AdminInvoice/edit/{publicId}` bewerkt uitsluitend concepts en slaat snapshots en regels tenantgebonden op.
- Header opslaan en regels toevoegen, inline wijzigen of verwijderen gebruiken declaratieve `AjaxEvent`-acties en verversen de editorcontainer met server-side herberekende totalen.
- `FinalizeInvoice` reserveert binnen dezelfde transactie een tenant- en jaargebonden nummer in `YYYY-0001`-vorm en maakt de factuur immutable.
- `/Flexgrid/AdminInvoice/detail/{publicId}` toont definitieve facturen met uitsluitend een bewerkbare factuurdatum voor het corrigeren van importfouten; definitieve overzichtsregels linken naar deze detailpagina.
- `/Flexgrid/AdminInvoice/pdf/{publicId}` genereert een definitieve factuur on-demand en opent deze inline; er wordt geen afgeleid PDF-bestand opgeslagen.
- `/Flexgrid/AdminInvoice/previewPdf/{publicId}` toont de opgeslagen conceptfactuur als PDF met actuele berekende totalen, zonder factuurnummer, statuswijziging of nummerreservering. Na wijzigingen aan de kopgegevens moet de gebruiker eerst opslaan.
- `/Flexgrid/AdminRecurringInvoice/preview` toont een read-only proefrun van de vandaag verschuldigde perioden. Dezelfde item-/projectregelkeuze wordt toegepast, inclusief verwachte totalen, overslagen en fouten; er wordt niets aangemaakt of verplaatst.
- `/Flexgrid/AdminInvoice/reviewQueue` verzamelt de nieuwste concepten voor menselijke controle, inclusief de herkomst van herhaalschema's en links naar editor en concept-PDF.
- Het definitieve factuurdetail biedt een expliciete e-mailactie met PDF-bijlage en, pas na de vervaldatum bij een nog openstaande factuur, een handmatige herinnering. Iedere verzendpoging krijgt een eigen record. Opnieuw mailen kan alleen na expliciete bevestiging; een recente poging met onbekende uitkomst blijft 15 minuten geblokkeerd.
- De PDF-template is selecteerbaar via `administration_invoice_pdf_template`. Projecttemplates uit `Templates/AdminInvoice/Pdf` overschrijven of vullen de moduletemplates aan.
- De standaard-PDF gebruikt bedrijfsgegevens en logo uit de algemene settings en leest primaire/secundaire merkkleuren uit `Files/Assets/Css/Variables.scss`.
- `CreateFullCreditNote` maakt een zelfstandig immutable creditdocument, spiegelt alle definitieve regels en btw-groepen negatief en markeert de bronfactuur binnen dezelfde transactie als `credited`.
- Creditnota's gebruiken de aparte reeks `credit_note` en nummeropmaak `CN-YYYY-0001`; deelcreditering is bewust nog niet impliciet ondersteund.
- Een definitieve factuur kan via een native bevestigingsdialoog en declaratieve Flexgrid-AJAX volledig worden gecrediteerd; een reden is verplicht.
- `/Flexgrid/AdminInvoice/creditNote/{publicId}` toont het immutable creditdocument en linkt terug naar de bronfactuur.
- `/Flexgrid/AdminInvoice/creditPdf/{publicId}` genereert de creditnota on-demand. `administration_credit_note_pdf_template` kan een eigen projecttemplate kiezen en valt terug op de factuurkeuze.
- Het overzicht kan op documenttype filteren; zoeken omvat factuurnummers, creditnotanummers, klanten, opmerkingen, creditredenen en bronfactuurnummers. Iedere rij linkt naar de juiste editor of immutable detailroute.
- In het overzicht kunnen conceptfacturen na bevestiging worden verwijderd. De factuurdatum van iedere factuur (geen creditnota) is inline via AJAX corrigeerbaar. De tenantgebonden use-case valideert de datum en laat nummer, regels, totalen en overige snapshots ongemoeid. Een correctie verandert een bestaand factuurnummer met jaaraanduiding niet.
- Samenvattingen worden per valuta gegroepeerd; bedragen van verschillende valuta worden nooit bij elkaar opgeteld.
- `Factuuritems` bewaren herbruikbare regelgegevens (omschrijving, aantal, eenheid, valuta, prijs excl. btw en btw-keuze). Een gekozen item wordt als bewerkbare snapshotregel gekopieerd; de regel bewaart de publieke item-id als herkomst, zonder foreign key.
- Een ongebruikt factuuritem kan in het beheer definitief worden verwijderd, na bevestiging. Verwijderen wordt geweigerd zodra het item op een factuurregel staat, aan een herhaalschema of klantkoppeling hangt, of varianten heeft. Varianten van een gekoppelde keuzegroep kunnen evenmin worden verwijderd. Een project kan extra externe verwijderblokkades registreren via `invoiceCatalogDeleteBlocker(array $item): ?string` in `Files/Boot/InvoiceFunctions.php`. Pauzeer een gebruikt item als het niet meer voor nieuwe facturen beschikbaar mag zijn.
- Een factuuritem kan ook een keuzegroep zijn. De groep heeft geen eigen prijs, bevat kinditems met variantsleutels en verwijst naar een geregistreerde projectfunctie. Bij een terugkerende run levert die functie `['catalog_item_public_id' => kind-id]` (optioneel met `quantity`) of `false` terug. AdminInvoice accepteert uitsluitend een actief direct kind, gebruikt diens catalogusprijs/btw en slaat bij `false` een lege periode over. In het klantpaneel wordt alleen de groep gekoppeld. Projectfuncties staan in `Files/Boot/InvoiceFunctions.php`; ook de factuur-CLI laadt dit bestand.
- Nieuwe `Herhaalschema's` bewaren alleen begindatum, maandinterval en betaaltermijn. Een klant kan via `AdminCustomer/edit` aan meerdere schema's worden gekoppeld; per klantkoppeling worden een of meer factuuritems beheerd. De koppeling bewaart ook de volgende factuurdatum, actieve status en laatste fout. Alle actieve items binnen een klantkoppeling komen samen op één concept. De dagtaak gebruikt `admin_recurring + schema-id + klant-id + datum` voor herhaalveilige aanmaak. Bestaande individuele schema's blijven werken met hun oorspronkelijke sleutel `schema-id + datum`.
- Bij het bewerken van een gedeeld herhaalschema staat een compacte lijst met gekoppelde klanten, itemaantal, volgende datum en status. Daar kun je een klant zoeken en koppelen, naar diens klantpagina gaan of de klant ontkoppelen. Ontkoppelen verwijdert de klantkoppeling en de bijbehorende schema-items; bestaande facturen blijven ongewijzigd. Een nieuwe koppeling krijgt vaste factuurregels nadat op de klantpagina items zijn toegevoegd; een optionele projectadapter kan ook zonder die items extra regels leveren.
- Een optionele `RecurringInvoiceLinesExtensionInterface` laat een project per toegewezen item variabele regels bepalen. Zonder projectadapter wordt de vaste catalogusregel gebruikt; de Admin-module importeert geen projectklasse. Een adapter kan daarnaast `RecurringInvoiceAdditionalLinesExtensionInterface` implementeren om na alle toegewezen items extra regels toe te voegen. Die hook draait ook wanneer de klant binnen een gedeeld schema geen item heeft: zonder extra regels wordt de periode overgeslagen, met extra regels ontstaat een concept in dezelfde proefrun en dagtaak. De adapter moet zelf dubbele kosten over meerdere schema's voorkomen. Bestaande gedeelde schema's met een vast item tonen dat item per klant. Bij de eerste itemwijziging of bij het opslaan van het gedeelde schema wordt het oude item zonder verlies naar alle bestaande klantkoppelingen overgenomen.
- Een gewijzigde itemprijs of klant verandert alleen volgende concepten. Een fout bij een gedeeld schema blijft bij de betreffende klantkoppeling staan en blokkeert andere klanten niet. Een schema en iedere klantkoppeling kunnen afzonderlijk worden gepauzeerd. Later koppelen of hervatten start op de eerstvolgende schemadatum en factureert geen gemiste perioden achteraf.

De pure domeinmodellen staan bewust onder `Domain/Model` en niet onder `src/Entity`: de map `Entity` is in Flexgrid gereserveerd voor classes die Autowire als persistente records scant.

## Opslagregels

- Geldbedragen: signed `BIGINT` in minor units.
- Hoeveelheden: signed `BIGINT` met vaste schaal 4.
- Btw-tarieven: integer basispunten (`2100` = 21%).
- Kalenderdatums: `YYYY-MM-DD`, onafhankelijk van tijdzone.
- Definitieve totalen en btw-groepen: snapshots; niet stilzwijgend herberekenen bij het tonen van historische documenten.
- Unique indexes bewaken publieke ids en factuurnummers per tenant.
- De ruwe `invoice_sequence_value` wordt naast het geformatteerde factuurnummer bewaard voor controleerbare toekomstige nummerconfiguratie.
- `source + external_id` is per tenant voorbereid voor idempotente webshop/importintegraties.

De persistence-adapter schrijft ontbrekende `invoice_number`, `source` en `external_id` als SQL `NULL`, niet als een lege string. Daardoor blokkeren de unique indexes geen meerdere normale conceptfacturen zonder externe referentie.

De gegenereerde recordrepositories zijn alleen de native Autowire metadata-/hydrationlaag. Alle publieke Invoice-lookups lopen via de expliciete PDO-adapter en vereisen een `TenantId`; er is geen tenantloze publieke finder.

## Configuratie

Een single-tenant installatie definieert een expliciete, stabiele tenant-id in de projectconfiguratie:

```php
define('__ADMIN_TENANT_ID__', 'mijn-administratie');
```

De controller leidt de tenant bewust niet af uit hostname, database of ingelogde gebruiker.

## Optionele Customer-integratie

AdminInvoice ontdekt conventiegebaseerd hooguit één `CustomerSelectionProvider`. De provider levert uitsluitend scalars voor de publieke bron-id, `PartySnapshot` en `AddressSnapshot`; Invoice bewaart zijn eigen versioned snapshots en krijgt geen foreign key of harde code-afhankelijkheid op Customer. Alle actieve klanten worden aangeboden; factuuradres en contactpersoon zijn optioneel. Handmatige invoer blijft beschikbaar wanneer de provider ontbreekt of de koppeling wordt gewist. Alleen de expliciete `Integration/Customer`-map implementeert het publieke klantdetailcontract; de Invoice-controller en kern importeren geen concrete Customer-code.

Een herhaalschema vereist een beschikbare klantprovider. De Invoice-kern en handmatige factuuraanmaak blijven zonder Customer bruikbaar. De geplande dagtaak is een application service; de hostinstallatie bepaalt hoe en wanneer die dagelijks wordt aangeroepen.

## Optionele Quote-integratie

Alleen `Integration/Quote/InvoiceConverter` kent het consumer-owned Quote-contract. De adapter ontvangt uitsluitend scalars, maakt via de normale `CreateInvoice`-use-case een concept en kopieert klant/adres, regels, btw, valuta, referentie en notities. Factuurdatum is de conversiedatum; de vervaldatum volgt `billing_invoice_due_days`. De publieke offerte-id wordt als externe bron-id opgeslagen, waardoor retries dezelfde factuur teruggeven.

## AJAX-richtlijn

Samengestelde acties zoals filteren, pagineren en regels muteren lopen via `AjaxEvent`, een declaratief `ajax="true"`-element en `AjaxResponse`. Module-JavaScript beheert alleen de interactie en start een normale form-submit; het implementeert geen eigen transport of responseafhandeling.

`AjaxDefinition` blijft beschikbaar voor een directe wijziging van één eenvoudige Autowire-entityproperty, mits direct opslaan geen tenant- of domeinregel omzeilt. Voor Invoice-records is dat niet automatisch veilig: de generieke handler gebruikt `findById()`, een setter en `save()`, terwijl Invoice expliciete `TenantContext`, statusovergangen, immutable finalisatie en herberekende totalen bewaakt. Zulke factuurmutaties lopen daarom via een tenantgebonden application use-case achter een `AjaxEvent`.

## PDF-templates

De meegeleverde fallback staat in `src/Templates/Pdf/default.php`. Voeg projectvarianten toe als `Templates/AdminInvoice/Pdf/{naam}.php`; veilige PHP-bestandsnamen worden automatisch zichtbaar in de algemene templatekeuze. Een projectbestand `default.php` vervangt ook de standaard zonder modulecode te wijzigen.

Templates ontvangen uitsluitend de scalar arrays `$invoice`, `$company` en `$brand`. De compatibele sleutel `$invoice['document_type']` is `invoice` of `credit_note`; voor een conceptpreview is `$invoice['is_preview']` waar en `$invoice['number']` leeg. De conceptpreview gebruikt bewust altijd de veilige meegeleverde moduletemplate, zodat een projectoverride de verplichte **CONCEPT / NIET BETALEN**-markering niet kan weglaten. Projecttemplates gelden voor definitieve PDF's. Templates voeren geen queries, settings-lookups of berekeningen uit. Remote resources blijven uit; het lokale logo wordt als data-URI aangeleverd. Wanneer de project-`Variables.scss` een kleur niet definieert, volgt eerst de gedeelde Flexgrid-variabele en daarna een vaste veilige fallback.

## Verzenden

E-mail gebruikt de bestaande `__MAIL_*__`-configuratie en verstuurt alleen na een expliciete beheerdersactie. Als `__MAIL_CATCH_ALL__` is ingesteld, wordt dit zichtbaar op het factuurdetail; e-mail gaat dan naar het testpostvak en niet naar de klant. `admin_invoice_dispatch` bewaart tenant, factuur, soort bericht, bedoeld klantadres en status per poging. De eerste poging gebruikt `invoice` of `reminder`; volgende pogingen krijgen een volgnummer in `kind` (bijvoorbeeld `invoice:2`), zodat de bestaande unieke index blijft werken en eerdere resultaten niet worden overschreven. Bij een herhaling bevestigt de beheerder dat hij de eerdere uitkomst heeft gecontroleerd en dat de klant het bericht mogelijk dubbel ontvangt. Een nog lopende poging kan pas na 15 minuten bewust worden herhaald. De projectfunctie voor terugkerende regels moet read-only zijn, omdat de proefrun haar eveneens aanroept.

Na toevoeging van `InvoiceDispatchRecord` is een bevoegde `force_aw=true`-scan nodig voordat het factuurdetail met verzendhistorie live kan werken.

## Testen

```text
php flexgrid/Modules/AdminInvoice/tests/run.php
php flexgrid/Modules/AdminInvoice/tests/InvoiceSchemaTest.php
php flexgrid/Modules/AdminInvoice/tests/PdoInvoiceRepositoryTest.php
php flexgrid/Modules/AdminInvoice/tests/PdoInvoiceListRepositoryTest.php
```

De tests bewaken onder meer nul regels, schaal-4 hoeveelheden, 9%/21%/verlegde btw, negatieve korting, afronding per regel, snapshotversies, vreemde valuta, immutable finalisatie, conceptmutaties, Autowire-kolomtypen, indexes en verboden module-imports. De databaseopdrachten controleren schema, aggregate-save, regelmetadata, persistence, filters, sortering, paginering, samenvattingen en tenantisolatie binnen volledig teruggerolde integratietransacties.

## Volgende slice

Payment, Customer-selectie, recente klantdocumenten en Quote-conversie zijn nu als begrensde module-integraties aangesloten. Deelcreditering blijft een afzonderlijke workflow; de volgende geplande administratiemodule is Expense.
