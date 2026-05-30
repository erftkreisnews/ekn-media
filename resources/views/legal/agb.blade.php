@extends('layouts.frontend')

@section('title', 'Allgemeine Geschäftsbedingungen (AGB) | Erftkreis News')
@section('meta_description', 'Allgemeine Geschäftsbedingungen (AGB) von Erftkreis News – Alexander Franz für die Lieferung und Lizenzierung journalistischer Inhalte.')
@section('canonical', url('/agb'))

@section('content')
    <section class="max-w-4xl mx-auto">
        <div class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8 shadow-sm">
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900">Allgemeine Geschäftsbedingungen (AGB)</h1>
            <p class="mt-2 text-sm sm:text-base text-gray-600">Erftkreis News – Alexander Franz</p>
            <p class="mt-4 text-sm text-gray-600">Stand: {{ now()->format('d.m.Y') }}</p>
        </div>

        <div class="mt-6 space-y-4 text-gray-800 leading-7">
            <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">§ 1 Geltungsbereich</h2>
                <p>Diese Allgemeinen Geschäftsbedingungen gelten für alle Geschäftsbeziehungen zwischen Erftkreis News – ein journalistisches Angebot von Alexander Franz (nachfolgend „Erftkreis News“) und ihren Kunden über die Lieferung und Lizenzierung von journalistischen Inhalten.</p>
                <p class="mt-3 font-medium text-gray-900">Erftkreis News erstellt und vertreibt insbesondere:</p>
                <div class="mt-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 sm:px-5 sm:py-4">
                    <ul class="list-disc pl-6 space-y-2 marker:text-ekn-700">
                        <li class="pl-1">redaktionelle Texte</li>
                        <li class="pl-1">Fotoaufnahmen</li>
                        <li class="pl-1">Videoaufnahmen</li>
                        <li class="pl-1">Audio- und Radiomaterial</li>
                        <li class="pl-1">Livestreams</li>
                        <li class="pl-1">Social-Media-Inhalte</li>
                    </ul>
                </div>
                <p class="mt-3">Diese AGB gelten ausschließlich gegenüber Unternehmern im Sinne von § 14 BGB.</p>
                <p class="mt-2">Abweichende Geschäftsbedingungen des Kunden werden nicht anerkannt, es sei denn, ihrer Geltung wird ausdrücklich schriftlich zugestimmt.</p>
            </section>

            <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">§ 2 Vertragsgegenstand</h2>
                <p>Gegenstand des Vertrages ist die Bereitstellung und Lizenzierung von journalistischem Material.</p>
                <p class="mt-2">Die Inhalte werden eigenständig und unabhängig erstellt.</p>
                <p class="mt-2">Ein Anspruch auf Lieferung bestimmter Inhalte besteht nicht.</p>
                <p class="mt-3 font-medium text-gray-900">Ein Vertrag kommt zustande durch:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1 marker:text-ekn-700">
                    <li>ausdrückliche Vereinbarung oder</li>
                    <li>tatsächliche Nutzung des bereitgestellten Materials</li>
                </ul>
            </section>

            <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">§ 3 Nutzungsrechte (Lizenz)</h2>
                <p>Sämtliche Inhalte sind urheberrechtlich geschützt.</p>
                <p class="mt-2">Erftkreis News räumt ein einfaches, nicht exklusives und nicht übertragbares Nutzungsrecht ein.</p>
                <p class="mt-3 font-medium text-gray-900">Das Nutzungsrecht ist beschränkt auf:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1 marker:text-ekn-700">
                    <li>das vereinbarte Medium</li>
                    <li>den vereinbarten Zweck</li>
                    <li>die vereinbarte Dauer</li>
                    <li>die vereinbarte Reichweite</li>
                </ul>
                <p class="mt-3 font-medium text-gray-900">Jede darüber hinausgehende Nutzung, insbesondere:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1 marker:text-ekn-700">
                    <li>Mehrfachveröffentlichung</li>
                    <li>Social-Media-Nutzung</li>
                    <li>Archiv- oder Zweitverwertung</li>
                    <li>Weitergabe an Dritte</li>
                </ul>
                <p class="mt-2">bedarf der vorherigen Zustimmung und ist zusätzlich honorarpflichtig.</p>
                <p class="mt-2">Erftkreis News ist berechtigt, Inhalte parallel an andere Kunden zu lizenzieren, sofern keine Exklusivität vereinbart wurde.</p>
                <p class="mt-2">Die Einräumung der Nutzungsrechte erfolgt erst nach vollständiger Zahlung.</p>
            </section>

            <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">§ 4 Vergütung / Lizenzhonorare</h2>
                <p>Jede Nutzung von Inhalten ist honorarpflichtig.</p>
                <p class="mt-2">Das Honorar stellt ein Nutzungsentgelt (Lizenzhonorar) dar und richtet sich insbesondere nach:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1 marker:text-ekn-700">
                    <li>Art des Inhalts (Text, Foto, Video, Audio)</li>
                    <li>Medium und Reichweite</li>
                    <li>Nutzungsdauer</li>
                    <li>Exklusivität</li>
                </ul>
                <p class="mt-2">Sofern keine individuelle Vereinbarung getroffen wurde, ist Erftkreis News berechtigt, ein angemessenes Honorar nach branchenüblichen Maßstäben festzusetzen.</p>
                <p class="mt-2">Das vereinbarte Honorar gilt ausschließlich für die konkret vereinbarte Nutzung.</p>
                <p class="mt-2">Jede weitergehende Nutzung ist erneut honorarpflichtig.</p>
                <p class="mt-2">Rechnungen sind sofort nach Erhalt ohne Abzug fällig.</p>
                <p class="mt-2">Im Falle unberechtigter Nutzung ist Erftkreis News berechtigt, ein erhöhtes Nutzungshonorar zu verlangen.</p>
            </section>

            <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">§ 5 Nutzungseinschränkungen</h2>
                <p class="font-medium text-gray-900">Ohne ausdrückliche Zustimmung ist unzulässig:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1 marker:text-ekn-700">
                    <li>Weitergabe an Dritte</li>
                    <li>Verkauf oder Unterlizenzierung</li>
                    <li>Bearbeitung oder Veränderung</li>
                    <li>Nutzung außerhalb des vereinbarten Kontexts</li>
                </ul>
                <p class="mt-3 font-medium text-gray-900">Dies gilt insbesondere für:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1 marker:text-ekn-700">
                    <li>Social-Media-Verwertung</li>
                    <li>Schnittfassungen oder Re-Edits</li>
                    <li>Plattformanpassungen</li>
                    <li>Weitergabe an Partnerunternehmen</li>
                </ul>
            </section>

            <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">§ 6 Urheberkennzeichnung</h2>
                <p class="font-medium text-gray-900">Bei jeder Nutzung ist eine eindeutige Quellenangabe erforderlich, z. B.:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1 marker:text-ekn-700">
                    <li>„Foto: Erftkreis News“</li>
                    <li>„Video: Erftkreis News“</li>
                </ul>
                <p class="mt-2">Eine fehlende Kennzeichnung berechtigt zur Nachforderung eines Zuschlags.</p>
            </section>

            <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">§ 7 Journalistische Nutzung und Kontextschutz</h2>
                <p class="font-medium text-gray-900">Die Inhalte dürfen nicht:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1 marker:text-ekn-700">
                    <li>sinnentstellend</li>
                    <li>verfälschend</li>
                    <li>diffamierend</li>
                    <li>vorverurteilend</li>
                </ul>
                <p class="mt-2">verwendet werden.</p>
                <p class="mt-2">Die Nutzung hat im Einklang mit journalistischen Standards zu erfolgen.</p>
            </section>

            <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">§ 8 Schutz von Persönlichkeitsrechten</h2>
                <p>Der Kunde ist verpflichtet, sämtliche gesetzlichen Bestimmungen zum Schutz von Persönlichkeitsrechten einzuhalten.</p>
                <p class="mt-3 font-medium text-gray-900">Insbesondere sind zu beachten:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1 marker:text-ekn-700">
                    <li>Recht am eigenen Bild</li>
                    <li>Unschuldsvermutung</li>
                    <li>Datenschutzrechtliche Vorgaben</li>
                </ul>
                <p class="mt-2">Die rechtliche Verantwortung für die Nutzung liegt beim Kunden.</p>
            </section>

            <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">§ 9 Pflicht zur Unkenntlichmachung</h2>
                <p>Personenbezogene Merkmale sind zu anonymisieren, sofern keine rechtliche Grundlage zur Veröffentlichung besteht.</p>
                <p class="mt-3 font-medium text-gray-900">Insbesondere sind unkenntlich zu machen:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1 marker:text-ekn-700">
                    <li>Kfz-Kennzeichen</li>
                    <li>Gesichter von Privatpersonen</li>
                    <li>Opfer von Straftaten oder Unglücken</li>
                    <li>Beschuldigte oder Tatverdächtige</li>
                    <li>sonstige identifizierende Merkmale</li>
                </ul>
                <p class="mt-2">Die Verantwortung hierfür liegt ausschließlich beim Kunden.</p>
            </section>

            <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">§ 10 KI-resistente Anonymisierung</h2>
                <p>Die Unkenntlichmachung muss so erfolgen, dass eine Identifizierung – auch unter Einsatz moderner Technologien wie künstlicher Intelligenz – ausgeschlossen ist.</p>
                <p class="mt-3 font-medium text-gray-900">Unzulässig sind insbesondere:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1 marker:text-ekn-700">
                    <li>einfacher Weichzeichner (Blur)</li>
                    <li>schwache Verpixelung</li>
                    <li>teilweise erkennbare Strukturen</li>
                </ul>
                <p class="mt-3 font-medium text-gray-900">Zulässig sind nur Verfahren mit irreversibler Wirkung, insbesondere:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1 marker:text-ekn-700">
                    <li>vollständige Maskierung</li>
                    <li>starke Pixelation</li>
                    <li>Inpainting</li>
                    <li>kombinierte Verfahren</li>
                </ul>
                <p class="mt-2">Eine Nutzung ohne ausreichende Anonymisierung ist unzulässig.</p>
            </section>

            <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">§ 11 Haftung</h2>
                <p>Erftkreis News haftet unbeschränkt bei Vorsatz und grober Fahrlässigkeit.</p>
                <p class="mt-2">Bei einfacher Fahrlässigkeit ist die Haftung auf den typischen, vorhersehbaren Schaden begrenzt.</p>
                <p class="mt-3 font-medium text-gray-900">Erftkreis News haftet nicht für:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1 marker:text-ekn-700">
                    <li>die konkrete Nutzung durch den Kunden</li>
                    <li>Verstöße gegen Persönlichkeitsrechte</li>
                    <li>fehlende Genehmigungen</li>
                </ul>
                <p class="mt-2">Der Kunde stellt Erftkreis News von Ansprüchen Dritter frei.</p>
            </section>

            <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">§ 12 Social Media Nutzung</h2>
                <p class="font-medium text-gray-900">Jede Nutzung auf Plattformen wie:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1 marker:text-ekn-700">
                    <li>Instagram</li>
                    <li>Facebook</li>
                    <li>TikTok</li>
                    <li>YouTube</li>
                </ul>
                <p class="mt-2">bedarf einer gesonderten Vereinbarung.</p>
                <p class="mt-3 font-medium text-gray-900">Dies gilt insbesondere für:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1 marker:text-ekn-700">
                    <li>Kurzclips</li>
                    <li>Re-Edits</li>
                    <li>plattformspezifische Anpassungen</li>
                </ul>
            </section>

            <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">§ 13 Archivierung und Verfügbarkeit</h2>
                <p>Eine dauerhafte Verfügbarkeit von Inhalten wird nicht gewährleistet.</p>
                <p class="mt-2">Erftkreis News ist berechtigt, Inhalte jederzeit aus dem Angebot zu entfernen.</p>
            </section>

            <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">§ 14 Schlussbestimmungen</h2>
                <p>Es gilt das Recht der Bundesrepublik Deutschland.</p>
                <p class="mt-2">Gerichtsstand ist der Sitz von Erftkreis News.</p>
                <p class="mt-2">Sollten einzelne Bestimmungen unwirksam sein, bleibt die Wirksamkeit der übrigen Regelungen unberührt.</p>
            </section>
        </div>
    </section>
@endsection
