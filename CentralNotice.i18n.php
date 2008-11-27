<?php
/**
 * Internationalisation file for CentralNotice extension.
 *
 * @addtogroup Extensions
 */

$messages = array();

$messages['en'] = array(
	'centralnotice' => 'Central notice admin',
	'noticetemplate' => 'Central notice template',
	'centralnotice-desc' => 'Adds a central sitenotice',
	'centralnotice-summary' => 'This module allows you to edit your currently setup central notices.
It can also be used to add or remove old notices.',
	// 'centralnotice-query' => 'Modify current notices',
	'centralnotice-notice-name' => 'Notice name',
	'centralnotice-end-date' => 'End date',
	'centralnotice-enabled' => 'Enabled',
	'centralnotice-modify' => 'Submit',
	// 'centralnotice-preview' => 'Preview',
	// 'centralnotice-add-new' => 'Add a new central notice',
	'centralnotice-remove' => 'Remove',
	'centralnotice-translate-heading' => 'Translation for $1',
	'centralnotice-manage' => 'Manage central notice',
	'centralnotice-add' => 'Add',
	'centralnotice-add-notice' => 'Add a notice',
	'centralnotice-add-template' => 'Add a template',
	// 'centralnotice-show-notices' => 'Show notices',
	// 'centralnotice-list-templates' => 'List templates',
	// 'centralnotice-translations' => 'Translations',
	// 'centralnotice-translate-to' => 'Translate to',
	// 'centralnotice-translate' => 'Translate',
	'centralnotice-english' => 'English',
	'centralnotice-template-name' => 'Template name',
	'centralnotice-templates' => 'Templates',
	'centralnotice-weight' => 'Weight',
	'centralnotice-locked' => 'Locked',
	'centralnotice-notices' => 'Notices',
	'centralnotice-notice-exists' => 'Notice already exists.
Not adding',
	'centralnotice-template-exists' => 'Template already exists.
Not adding',
	'centralnotice-notice-doesnt-exist' => 'Notice does not exist.
Nothing to remove',
	'centralnotice-template-still-bound' => 'Template is still bound to a notice.
Not removing.',
	// 'centralnotice-template-body' => 'Template body:',
	'centralnotice-day' => 'Day',
	'centralnotice-year' => 'Year',
	'centralnotice-month' => 'Month',
	'centralnotice-hours' => 'Hour',
	'centralnotice-min' => 'Minute',
	'centralnotice-project-lang' => 'Project language',
	'centralnotice-project-name' => 'Project name',
	'centralnotice-start-date' => 'Start date',
	'centralnotice-start-time' => 'Start time (UTC)',
	'centralnotice-assigned-templates' => 'Assigned templates',
	'centralnotice-no-templates' => 'No templates found.
Add some!',
	'centralnotice-no-templates-assigned' => 'No templates assigned to notice.
Add some!',
	'centralnotice-available-templates' => 'Available templates',
	'centralnotice-template-already-exists' => 'Template is already tied to campaing.
Not adding',
	'centralnotice-preview-template' => 'Preview template',
	'centralnotice-start-hour' => 'Start time',
	'centralnotice-change-lang' => 'Change translation language',
	'centralnotice-weights' => 'Weights',
	'centralnotice-notice-is-locked' => 'Notice is locked.
Not removing',
	// 'centralnotice-overlap' => 'Notice overlaps within the time of another notice.
// Not adding',
	'centralnotice-invalid-date-range' => 'Invalid date range.
Not updating',
	'centralnotice-null-string' => 'Cannot add a null string.
Not adding',
	'centralnotice-confirm-delete' => 'Are you sure you want to delete this item?
This action will be unrecoverable.',
	'centralnotice-no-notices-exist' => 'No notices exist.
Add one below',
	// 'centralnotice-no-templates-translate' => 'There are not any templates to edit translations for',
	'centralnotice-number-uses' => 'Uses',
	'centralnotice-edit-template' => 'Edit template',
	'centralnotice-message' => 'Message',
	'centralnotice-message-not-set' => 'Message not set',
	'centralnotice-clone' => 'Clone',
	'centralnotice-clone-notice' => 'Create a copy of the template',
	'centralnotice-preview-all-template-translations' => 'Preview all available translations of template',

	'right-centralnotice_admin_rights' => 'Manage central notices',
	'right-centralnotice_translate_rights' => 'Translate central notices',

	'action-centralnotice_admin_rights' => 'manage central notices',
	'action-centralnotice_translate_rights' => 'translate central notices',
);

/** Message documentation (Message documentation)
 * @author Darth Kule
 * @author Jon Harald Søby
 * @author Purodha
 */
$messages['qqq'] = array(
	'centralnotice' => 'Name of Special:CentralNotice in Special:SpecialPages and title of the page',
	'noticetemplate' => 'Title of Special:NoticeTemplate',
	'centralnotice-desc' => 'Short description of the Centralnotice extension, shown in [[Special:Version]]. Do not translate or change links.',
	'centralnotice-summary' => 'Used in Special:CentralNotice',
	'centralnotice-modify' => '{{Identical|Submit}}',
	'centralnotice-preview' => '{{Identical|Preview}}',
	'centralnotice-remove' => '{{Identical|Remove}}',
	'centralnotice-add' => '{{Identical|Add}}',
	'centralnotice-translate' => '{{Identical|Translate}}',
	'centralnotice-notice-exists' => 'Errore message displayed in Special:CentralNotice when trying to add a notice with the same name of another notice',
	'centralnotice-template-exists' => 'Errore message displayed in Special:NoticeTemplate when trying to add a template with the same name of another template',
	'centralnotice-start-date' => 'Used in Special:CentralNotice',
	'centralnotice-start-time' => 'Used in Special:CentralNotice',
	'centralnotice-available-templates' => 'Used in Special:NoticeTemplate',
	'centralnotice-notice-is-locked' => 'Errore message displayed in Special:CentralNotice when trying to delete a locked notice',
	'centralnotice-no-notices-exist' => 'Used in Special:CentralNotice when there are no notices',
	'right-centralnotice_admin_rights' => '{{doc-right}}',
	'right-centralnotice_translate_rights' => '{{doc-right}}',
	'action-centralnotice_admin_rights' => '{{doc-action}}',
	'action-centralnotice_translate_rights' => '{{doc-action}}',
);

/** Afrikaans (Afrikaans)
 * @author Naudefj
 */
$messages['af'] = array(
	'centralnotice' => 'Bestuur sentrale kennisgewings',
	'noticetemplate' => 'Sjabloon vir sentrale kennisgewing',
	'centralnotice-desc' => "Voeg 'n sentrale stelselkennisgewing by",
	'centralnotice-end-date' => 'Einddatum',
	'centralnotice-enabled' => 'Aktief',
	'centralnotice-modify' => 'Dien in',
	'centralnotice-preview' => 'Voorskou',
	'centralnotice-add-new' => "Voeg 'n nuwe sentrale kennisgewing by",
	'centralnotice-remove' => 'Verwyder',
	'centralnotice-translate-heading' => 'Vertaling vir $1',
	'centralnotice-manage' => 'Beheer sentrale kennisgewings',
	'centralnotice-add' => 'Byvoeg',
	'centralnotice-add-template' => 'Voeg sjabloon by',
	'centralnotice-show-notices' => 'Wys kennisgewings',
	'centralnotice-list-templates' => 'Lys sjablone',
	'centralnotice-translations' => 'Vertalings',
	'centralnotice-translate-to' => 'Vertaal na',
	'centralnotice-translate' => 'Vertaal',
	'centralnotice-english' => 'Engels',
	'centralnotice-template-name' => 'Sjabloonnaam',
	'centralnotice-templates' => 'Sjablone',
	'centralnotice-weight' => 'Gewig',
	'centralnotice-locked' => 'Gesluit',
	'centralnotice-template-body' => 'Sjablooninhoud:',
	'centralnotice-day' => 'Dag',
	'centralnotice-year' => 'Jaar',
	'centralnotice-month' => 'Maand',
	'centralnotice-hours' => 'Uur',
	'centralnotice-min' => 'Minuut',
	'centralnotice-project-lang' => 'Projektaal',
	'centralnotice-project-name' => 'Projeknaam',
	'centralnotice-start-date' => 'Begindatum',
	'centralnotice-start-time' => 'Begintyd (UTC)',
	'centralnotice-assigned-templates' => 'Aangewese sjablone',
	'centralnotice-start-hour' => 'Begintyd',
	'centralnotice-change-lang' => 'Verander taal vir vertaling',
	'centralnotice-weights' => 'Gewigte',
	'centralnotice-number-uses' => 'Aantal kere gebruik',
	'centralnotice-edit-template' => 'Wysig sjabloon',
	'centralnotice-message' => 'Boodskap',
	'right-centralnotice_admin_rights' => 'Bestuur sentrale kennisgewings',
	'action-centralnotice_admin_rights' => 'bestuur sentrale kennisgewings',
	'action-centralnotice_translate_rights' => 'vertaal sentrale kennisgewings',
);

/** Amharic (አማርኛ)
 * @author Elfalem
 */
$messages['am'] = array(
	'centralnotice-desc' => 'በሁሉም ገጾች ላይ የሚታይ መልዕክት ይጨምራል',
);

/** Aragonese (Aragonés)
 * @author Juanpabl
 */
$messages['an'] = array(
	'centralnotice-desc' => 'Adibe una "sitenotice" zentral',
);

/** Arabic (العربية)
 * @author Meno25
 * @author OsamaK
 */
$messages['ar'] = array(
	'centralnotice' => 'مدير الإخطار المركزي',
	'noticetemplate' => 'قالب الإخطار المركزي',
	'centralnotice-desc' => 'يضيف إعلانا مركزيا للموقع',
	'centralnotice-summary' => 'هذه الوحدة تسمح لك بتعديل إعدادات الإخطار المركزي الحالية.
يمكن أن تستخدم أيضا لإضافة أو إزالة إخطارات قديمة.',
	'centralnotice-query' => 'تعديل الإخطارات الحالية',
	'centralnotice-notice-name' => 'اسم الإخطار',
	'centralnotice-end-date' => 'تاريخ الانتهاء',
	'centralnotice-enabled' => 'مُفعّل',
	'centralnotice-modify' => 'سلّم',
	'centralnotice-preview' => 'عاين',
	'centralnotice-add-new' => 'أضف إخطار جديد مركزي',
	'centralnotice-remove' => 'أزل',
	'centralnotice-translate-heading' => 'ترجمة $1',
	'centralnotice-manage' => 'أدر الإخطار المركزي',
	'centralnotice-add' => 'أضف',
	'centralnotice-add-notice' => 'إضافة إخطار',
	'centralnotice-add-template' => 'إضافة قالب',
	'centralnotice-show-notices' => 'إظهار الإخطارات',
	'centralnotice-translations' => 'الترجمات',
	'centralnotice-translate-to' => 'ترجم إلى',
	'centralnotice-translate' => 'ترجم',
	'centralnotice-english' => 'الإنجليزية',
	'centralnotice-template-name' => 'اسم القالب',
	'centralnotice-templates' => 'القوالب',
	'centralnotice-weight' => 'الوزن',
	'centralnotice-locked' => 'مغلق',
	'centralnotice-notices' => 'الإخطارات',
	'centralnotice-notice-exists' => 'الإخطار موجود بالفعل.
لا إضافة',
	'centralnotice-template-body' => 'جسم القالب:',
	'centralnotice-day' => 'اليوم',
	'centralnotice-year' => 'السنة',
	'centralnotice-month' => 'الشهر',
	'centralnotice-hours' => 'الساعة',
	'centralnotice-min' => 'الدقيقة',
	'centralnotice-project-lang' => 'لغة المشروع',
	'centralnotice-project-name' => 'اسم المشروع',
	'centralnotice-start-date' => 'تاريخ البدء',
	'centralnotice-start-time' => 'وقت البدء (غرينتش)',
	'centralnotice-no-templates' => 'لا قوالب موجود.
أضف بعضا منها!',
	'centralnotice-available-templates' => 'القوالب المتاحة',
	'centralnotice-preview-template' => 'معاينة القالب',
	'centralnotice-start-hour' => 'وقت البدء',
	'centralnotice-change-lang' => 'تغيير لغة الترجمة',
	'centralnotice-weights' => 'الأوزان',
	'centralnotice-notice-is-locked' => 'الإخطار مغلق.
لا إزالة',
	'centralnotice-overlap' => 'الإخطار يتداخل مع وقت إخطار آخر.
لا إضافة',
	'centralnotice-invalid-date-range' => 'مدى تاريخ غير صحيح.
لا تحديث',
	'centralnotice-null-string' => 'لا يمكن إضافة نص مصفّر.
لا إضافة',
	'centralnotice-confirm-delete' => 'هل أنت متأكد من حذف هذا العنصر؟
هذا الإجراء لن يكون قابلا للاسترجاع',
	'centralnotice-no-notices-exist' => 'لا إخطارات موجودة.
أضف واحدا أسفله',
	'centralnotice-no-templates-translate' => 'لا يوجد أي قالب لتحرير ترجمته',
	'centralnotice-number-uses' => 'الاستخدامات',
	'centralnotice-edit-template' => 'حرّر قالبا',
	'centralnotice-message' => 'الرسالة',
	'centralnotice-message-not-set' => 'الرسالة غير مضبوطة',
	'right-centralnotice_admin_rights' => 'أدر الإخطارات المركزية',
	'right-centralnotice_translate_rights' => 'ترجم الإخطارات المركزية',
);

/** Egyptian Spoken Arabic (مصرى)
 * @author Ghaly
 * @author Meno25
 * @author Ramsis II
 */
$messages['arz'] = array(
	'centralnotice' => 'مدير الاعلانات المركزية',
	'noticetemplate' => 'قالب الاعلانات المركزية',
	'centralnotice-desc' => 'بيحط اعلان مركزى للموقع',
	'centralnotice-summary' => 'الوحدة دى بتسمحلك بتعديل إعدادات الإخطار المركزي الحالية.
ممكن تستخدم كمان لإضافة أو إزالة إخطارات قديمة.',
	'centralnotice-notice-name' => 'اسم الاعلان',
	'centralnotice-end-date' => 'تاريخ الانتهاء',
	'centralnotice-enabled' => 'متشغل',
	'centralnotice-modify' => 'قدم',
	'centralnotice-remove' => 'شيل',
	'centralnotice-translate-heading' => 'الترجمة بتاعة $1',
	'centralnotice-manage' => 'ادارة الاعلانات المركزية',
	'centralnotice-add' => 'ضيف',
	'centralnotice-add-notice' => 'حط اعلان',
	'centralnotice-add-template' => 'ضيف قالب',
	'centralnotice-english' => 'انجليزى',
	'centralnotice-template-name' => 'اسم القالب',
	'centralnotice-templates' => 'قوالب',
	'centralnotice-weight' => 'الوزن',
	'centralnotice-locked' => 'مقفول',
	'centralnotice-notices' => 'اعلانات',
	'centralnotice-notice-exists' => 'الاعلان موجود من قبل كده.
مافيش اصافة',
	'centralnotice-template-exists' => 'القالب موجود من قبل كده
مافيش اضافة',
	'centralnotice-notice-doesnt-exist' => 'الاعلان مش موجود
مافيش حاجة عشان تتشال',
	'centralnotice-template-still-bound' => 'القالب لسة مربوط بالاعلان.
ماينفعش يتشال.',
	'centralnotice-day' => 'اليوم',
	'centralnotice-year' => 'السنه',
	'centralnotice-month' => 'الشهر',
	'centralnotice-hours' => 'الساعة',
	'centralnotice-min' => 'الدقيقة',
	'centralnotice-project-lang' => 'اللغة بتاعة المشروع',
	'centralnotice-project-name' => 'الاسم بتاع المشروع',
	'centralnotice-start-date' => 'تاريخ البدايه',
	'centralnotice-start-time' => 'وقت البداية(يو تي سي)',
	'centralnotice-assigned-templates' => 'قالب موجود',
	'centralnotice-no-templates' => 'مافيش قوالب.
ضيف بعض القوالب!',
	'centralnotice-no-templates-assigned' => ' مافيش قالب موجود.
ضيف  قوالب',
	'centralnotice-available-templates' => 'القوالب الموجودة',
	'centralnotice-template-already-exists' => 'قالب موجود
. مافيش  إضافة',
	'centralnotice-preview-template' => 'معاينة القالب',
	'centralnotice-start-hour' => 'وقت البداية',
	'centralnotice-change-lang' => 'تغيير لغة الترجمه',
	'centralnotice-weights' => 'الاوزان',
	'centralnotice-notice-is-locked' => 'الاعلان مقفول.
مافيش مسح.',
	'centralnotice-invalid-date-range' => 'مدى تاريخ مش صحيح.
مافيش تحديث',
	'centralnotice-null-string' => 'مش ممكن إضافة نص مصفّر.
مافيش  إضافة',
	'centralnotice-confirm-delete' => 'انت متأكد انك عايز تلغى الحتة دي؟
الاجراء دا مش ح يترجع فيه',
	'centralnotice-no-notices-exist' => 'مافيش اخطارات موجودة.
ضيف واحد تحته',
	'centralnotice-number-uses' => 'الاستعمالات',
	'centralnotice-edit-template' => 'عدل في القالب',
	'centralnotice-message' => 'الرسالة',
	'centralnotice-message-not-set' => 'الرسالة مش مظبوطة',
	'centralnotice-clone' => 'انسخ',
	'centralnotice-clone-notice' => 'اعمل نسخة من القالب',
	'centralnotice-preview-all-template-translations' => 'اعرض كل الترجمات الموجودة للقالب',
	'right-centralnotice_admin_rights' => 'ادارة الاعلانات المركزيه',
	'right-centralnotice_translate_rights' => 'ترجم الاعلانات المركزية',
	'action-centralnotice_admin_rights' => 'ادارة الاعلانات المركزية',
	'action-centralnotice_translate_rights' => 'ترجمة الاعلانات المركزية',
);

/** Asturian (Asturianu)
 * @author Esbardu
 */
$messages['ast'] = array(
	'centralnotice-desc' => 'Añade una anuncia centralizada del sitiu (sitenotice)',
);

/** Southern Balochi (بلوچی مکرانی)
 * @author Mostafadaneshvar
 */
$messages['bcc'] = array(
	'centralnotice-desc' => 'یک مرکزی اخطار سایت هور کنت',
);

/** Belarusian (Taraškievica orthography) (Беларуская (тарашкевіца))
 * @author EugeneZelenko
 */
$messages['be-tarask'] = array(
	'centralnotice-add' => 'Дадаць',
);

/** Bulgarian (Български)
 * @author Borislav
 * @author DCLXVI
 */
$messages['bg'] = array(
	'centralnotice-desc' => 'Добавя главнa сайтова бележка',
	'centralnotice-end-date' => 'Крайна дата',
	'centralnotice-modify' => 'Изпращане',
	'centralnotice-preview' => 'Преглеждане',
	'centralnotice-remove' => 'Премахване',
	'centralnotice-translate-heading' => 'Превод за $1',
	'centralnotice-add' => 'Добавяне',
	'centralnotice-add-template' => 'Добавяне на шаблон',
	'centralnotice-translations' => 'Преводи',
	'centralnotice-translate-to' => 'Превеждане на',
	'centralnotice-translate' => 'Превеждане',
	'centralnotice-english' => 'Английски',
	'centralnotice-template-name' => 'Име на шаблона',
	'centralnotice-templates' => 'Шаблони',
	'centralnotice-day' => 'Ден',
	'centralnotice-year' => 'Година',
	'centralnotice-month' => 'Месец',
	'centralnotice-hours' => 'Час',
	'centralnotice-min' => 'Минута',
	'centralnotice-start-date' => 'Начална дата',
	'centralnotice-start-time' => 'начално време (UTC)',
	'centralnotice-available-templates' => 'Налични шаблони',
	'centralnotice-start-hour' => 'Начален час',
	'centralnotice-edit-template' => 'Редактиране на шаблон',
	'centralnotice-message' => 'Съобщение',
);

/** Bengali (বাংলা)
 * @author Bellayet
 */
$messages['bn'] = array(
	'centralnotice-desc' => 'একটি কেন্দ্রীয় সাইটনোটিশ যোগ করো',
);

/** Breton (Brezhoneg)
 * @author Fulup
 */
$messages['br'] = array(
	'centralnotice-desc' => "Ouzhpennañ a ra ur c'hemenn kreiz e laez ar pajennoù (sitenotice).",
);

/** Bosnian (Bosanski)
 * @author CERminator
 */
$messages['bs'] = array(
	'noticetemplate' => 'Šablon za središnju obavijest',
	'centralnotice-desc' => 'Dodaje središnju obavijest na stranici',
	'centralnotice-end-date' => 'Krajnji datum',
	'centralnotice-enabled' => 'Omogućeno',
	'centralnotice-modify' => 'Pošalji',
	'centralnotice-preview' => 'Izgled',
	'centralnotice-remove' => 'Ukloni',
	'centralnotice-translate-heading' => 'Prijevod za $1',
	'centralnotice-add' => 'Dodaj',
	'centralnotice-add-notice' => 'Dodaj obavještenje',
	'centralnotice-show-notices' => 'Prikaži obavještenja',
	'centralnotice-translations' => 'Prijevodi',
	'centralnotice-translate' => 'Prijevod',
	'centralnotice-english' => 'engleski jezik',
	'centralnotice-template-name' => 'Naslov šablona',
	'centralnotice-templates' => 'Šabloni',
	'centralnotice-locked' => 'Zaključano',
	'centralnotice-notices' => 'Obavještenja',
	'centralnotice-template-body' => 'Tijelo šablona:',
	'centralnotice-day' => 'dan',
	'centralnotice-year' => 'godina',
	'centralnotice-month' => 'mjesec',
	'centralnotice-hours' => 'sat',
	'centralnotice-min' => 'minut',
	'centralnotice-project-lang' => 'Jezik projekta',
	'centralnotice-project-name' => 'Naslov projekta',
	'centralnotice-start-date' => 'Početni datum',
	'centralnotice-start-time' => 'Početno vrijeme (UTC)',
	'centralnotice-available-templates' => 'Dostupni šabloni',
	'centralnotice-preview-template' => 'Izgled šablona',
	'centralnotice-start-hour' => 'Vrijeme početka',
	'centralnotice-change-lang' => 'Promjena jezika prijevoda',
	'centralnotice-weights' => 'Težina',
	'centralnotice-notice-is-locked' => 'Obavještenje je zaključano.
Ne može se ukloniti',
	'centralnotice-overlap' => 'Obavještenje se preklapa u toku vremena sa drugim obavještenjem.
Ne može se dodati',
	'centralnotice-invalid-date-range' => 'Pogrešan vremenski period.
Ne može se ažurirati',
	'centralnotice-no-notices-exist' => 'Ne postoji obavijest.
Dodaj jednu ispod',
	'centralnotice-number-uses' => 'Upotreba',
	'centralnotice-edit-template' => 'Uredi šablon',
	'centralnotice-message' => 'Poruka',
	'centralnotice-message-not-set' => 'Poruka nije postavljena',
	'right-centralnotice_translate_rights' => 'Prevođenje središnjeg obavještenja',
	'action-centralnotice_translate_rights' => 'Prevođenje središnjeg obavještenja',
);

/** Czech (Česky)
 * @author Li-sung
 * @author Mormegil
 */
$messages['cs'] = array(
	'centralnotice' => 'Správa centralizovaných oznámení',
	'noticetemplate' => 'Šablony centralizovaných oznámení',
	'centralnotice-desc' => 'Přidává centrální zprávu do záhlaví',
	'centralnotice-summary' => 'Pomocí tohoto modulu můžete upravovat momentálně nastavená centralizovaná oznámení.
Také zde můžete přidávat nová či odstraňovat stará.',
	'centralnotice-notice-name' => 'Název oznámení',
	'centralnotice-end-date' => 'Datum konce',
	'centralnotice-enabled' => 'Zapnuto',
	'centralnotice-modify' => 'Odeslat',
	'centralnotice-remove' => 'Odstranit',
	'centralnotice-translate-heading' => 'Překlad šablony „$1“',
	'centralnotice-manage' => 'Spravovat centralizovaná oznámení',
	'centralnotice-add' => 'Přidat',
	'centralnotice-add-notice' => 'Přidat oznámení',
	'centralnotice-add-template' => 'Přidat šablonu',
	'centralnotice-english' => 'Anglicky',
	'centralnotice-template-name' => 'Název šablony',
	'centralnotice-templates' => 'Šablony',
	'centralnotice-weight' => 'Váha',
	'centralnotice-locked' => 'Uzamčeno',
	'centralnotice-notices' => 'Oznámení',
	'centralnotice-notice-exists' => 'Oznámení už existuje. Nepřidáno.',
	'centralnotice-template-exists' => 'Šablona už existuje. Nepřidána.',
	'centralnotice-notice-doesnt-exist' => 'Oznámení neexistuje. Není co odstranit.',
	'centralnotice-template-still-bound' => 'Šablona je stále navázána na oznámení. Nebude odstraněna.',
	'centralnotice-day' => 'Den',
	'centralnotice-year' => 'Rok',
	'centralnotice-month' => 'Měsíc',
	'centralnotice-hours' => 'Hodiny',
	'centralnotice-min' => 'Minuty',
	'centralnotice-project-lang' => 'Jazyk projektu',
	'centralnotice-project-name' => 'Název projektu',
	'centralnotice-start-date' => 'Datum začátku',
	'centralnotice-start-time' => 'Čas začátku (UTC)',
	'centralnotice-assigned-templates' => 'Přiřazené šablony',
	'centralnotice-no-templates' => 'Nenalezena ani jedna šablona. Vytvořte nějakou!',
	'centralnotice-no-templates-assigned' => 'K oznámení nebyly přiřazeny žádné šablony. Přidejte nějaké!',
	'centralnotice-available-templates' => 'Dostupné šablony',
	'centralnotice-template-already-exists' => 'Šablona už byla s kampaní svázána.
Nebude přidána.',
	'centralnotice-start-hour' => 'Čas začátku',
	'centralnotice-change-lang' => 'Změnit překládaný jazyk',
	'centralnotice-weights' => 'Váhy',
	'centralnotice-notice-is-locked' => 'Oznámení je uzamčeno. Nebude odstraněno.',
	'centralnotice-invalid-date-range' => 'Neplatný rozsah dat. Nebude změněno.',
	'centralnotice-null-string' => 'Nelze přidat prázdný řetězec. Nebude přidáno.',
	'centralnotice-confirm-delete' => 'Jste si jisti, že chcete tuto položku smazat? Tuto operaci nebude možno vrátit.',
	'centralnotice-no-notices-exist' => 'Neexistují žádná oznámení.
Níže můžete vytvořit nové.',
	'centralnotice-number-uses' => 'Použití',
	'centralnotice-edit-template' => 'Upravit šablonu',
	'centralnotice-message' => 'Zpráva',
	'centralnotice-message-not-set' => 'Zpráva nebyla nastavena',
	'centralnotice-clone' => 'Naklonovat',
	'centralnotice-clone-notice' => 'Vytvořit kopii šablony',
	'centralnotice-preview-all-template-translations' => 'Náhled všech dostupných překladů šablony',
	'right-centralnotice_admin_rights' => 'Správa centralizovaných oznámení',
	'right-centralnotice_translate_rights' => 'Překlad centralizovaných oznámení',
	'action-centralnotice_admin_rights' => 'spravovat centralizovaná oznámení',
	'action-centralnotice_translate_rights' => 'překládat centralizovaná oznámení',
);

/** German (Deutsch)
 * @author Metalhead64
 * @author Purodha
 * @author Raimond Spekking
 */
$messages['de'] = array(
	'centralnotice' => 'Administrierung der zentralen Meldungen',
	'noticetemplate' => 'Zentrale Meldungs-Vorlage',
	'centralnotice-desc' => "Fügt eine zentrale ''sitenotice'' hinzu",
	'centralnotice-summary' => 'Diese Erweiterung erlaubt die Konfiguration zentraler Meldungen.
Sie kann auch zur Erstellung neuer und Löschung alter Meldungen verwendet werden.',
	'centralnotice-query' => 'Aktuelle Meldung ändern',
	'centralnotice-notice-name' => 'Name der Notiz',
	'centralnotice-end-date' => 'Enddatum',
	'centralnotice-enabled' => 'Aktiviert',
	'centralnotice-modify' => 'OK',
	'centralnotice-preview' => 'Vorschau',
	'centralnotice-add-new' => 'Füge eine neue zentrale Meldung hinzu',
	'centralnotice-remove' => 'Entfernen',
	'centralnotice-translate-heading' => 'Übersetzung von „$1“',
	'centralnotice-manage' => 'Zentrale Meldungen verwalten',
	'centralnotice-add' => 'Hinzufügen',
	'centralnotice-add-notice' => 'Hinzufügen einer Meldung',
	'centralnotice-add-template' => 'Hinzufügen einer Vorlage',
	'centralnotice-show-notices' => 'Zeige Meldungen',
	'centralnotice-list-templates' => 'Vorlagen auflisten',
	'centralnotice-translations' => 'Übersetzungen',
	'centralnotice-translate-to' => 'Übersetzen in',
	'centralnotice-translate' => 'Übersetzen',
	'centralnotice-english' => 'Englisch',
	'centralnotice-template-name' => 'Name der Vorlage',
	'centralnotice-templates' => 'Vorlagen',
	'centralnotice-weight' => 'Gewicht',
	'centralnotice-locked' => 'Gesperrt',
	'centralnotice-notices' => 'Meldungen',
	'centralnotice-notice-exists' => 'Meldung ist bereits vorhanden.
Nicht hinzugefügt.',
	'centralnotice-template-exists' => 'Vorlage ist bereits vorhanden.
Nicht hinzugefügt.',
	'centralnotice-notice-doesnt-exist' => 'Meldung ist nicht vorhanden.
Entfernung nicht möglich.',
	'centralnotice-template-still-bound' => 'Vorlage ist noch an eine Meldung gebunden.
Entfernung nicht möglich.',
	'centralnotice-template-body' => 'Vorlagentext:',
	'centralnotice-day' => 'Tag',
	'centralnotice-year' => 'Jahr',
	'centralnotice-month' => 'Monat',
	'centralnotice-hours' => 'Stunde',
	'centralnotice-min' => 'Minute',
	'centralnotice-project-lang' => 'Projektsprache',
	'centralnotice-project-name' => 'Projektname',
	'centralnotice-start-date' => 'Startdatum',
	'centralnotice-start-time' => 'Startzeit (UTC)',
	'centralnotice-assigned-templates' => 'Zugewiesene Vorlagen',
	'centralnotice-no-templates' => 'Es sind keine Vorlagen im System vorhanden.',
	'centralnotice-no-templates-assigned' => 'Es sind keine Vorlagen an Meldungen zugewiesen.
Füge eine hinzu.',
	'centralnotice-available-templates' => 'Verfügbare Vorlagen',
	'centralnotice-template-already-exists' => 'Vorlage ist bereits an die Kampagne gebunden.
Nicht hinzugefügt.',
	'centralnotice-preview-template' => 'Vorschau Vorlage',
	'centralnotice-start-hour' => 'Startzeit',
	'centralnotice-change-lang' => 'Übersetzungssprache ändern',
	'centralnotice-weights' => 'Gewicht',
	'centralnotice-notice-is-locked' => 'Meldung ist gesperrt.
Entfernung nicht möglich.',
	'centralnotice-overlap' => 'Die Meldung überschneidet sich mit dem Zeitraum einer anderen Meldung.
Nicht hinzugefügt.',
	'centralnotice-invalid-date-range' => 'Ungültiger Zeitraum.
Nicht aktualisiert.',
	'centralnotice-null-string' => 'Es kann kein Nullstring hinzugefügt werden.
Nichts hinzugefügt.',
	'centralnotice-confirm-delete' => 'Bist du sicher, dass du den Eintrag löschen möchtest?
Die Aktion kann nicht rückgängig gemacht werden.',
	'centralnotice-no-notices-exist' => 'Es sind keine Meldungen vorhanden.
Füge eine hinzu.',
	'centralnotice-no-templates-translate' => 'Es gibt keine Vorlagen, für die Übersetzungen zu bearbeiten wären',
	'centralnotice-number-uses' => 'Nutzungen',
	'centralnotice-edit-template' => 'Vorlage bearbeiten',
	'centralnotice-message' => 'Nachricht',
	'centralnotice-message-not-set' => 'Nachricht nicht gesetzt',
	'centralnotice-clone' => 'Klon erstellen',
	'centralnotice-clone-notice' => 'Erstelle eine Kopie der Vorlage',
	'centralnotice-preview-all-template-translations' => 'Vorschau aller verfügbaren Übersetzungen einer Vorlage',
	'right-centralnotice_admin_rights' => 'Verwalten von zentralen Meldungen',
	'right-centralnotice_translate_rights' => 'Übersetzen von zentralen Meldungen',
	'action-centralnotice_admin_rights' => 'Zentrale Seitennotiz verwalten',
	'action-centralnotice_translate_rights' => 'Zentrale Seitennotiz übersetzen',
);

/** Lower Sorbian (Dolnoserbski)
 * @author Michawiki
 */
$messages['dsb'] = array(
	'centralnotice-desc' => 'Pśidawa centralnu powěźeńku do głowy boka',
);

/** Greek (Ελληνικά)
 * @author Badseed
 * @author Lou
 * @author ZaDiak
 */
$messages['el'] = array(
	'noticetemplate' => 'Πρότυπο κεντρικής ανακοίνωσης',
	'centralnotice-desc' => 'Προσθέτει μια κεντρική ανακοίνωση',
	'centralnotice-end-date' => 'Ημερομηνία λήξης',
	'centralnotice-modify' => 'Καταχώρηση',
	'centralnotice-preview' => 'Προεπισκόπηση',
	'centralnotice-add-new' => 'Προσθήκη νέας κεντρικής ανακοίνωσης',
	'centralnotice-add-notice' => 'Προσθήκη ανακοίνωσης',
	'centralnotice-add-template' => 'Προσθήκη προτύπου',
	'centralnotice-show-notices' => 'Εμφάνιση ανακοινώσεων',
	'centralnotice-list-templates' => 'Κατάλογος προτύπων',
	'centralnotice-translations' => 'Μεταφράσεις',
	'centralnotice-template-name' => 'Όνομα προτύπου',
	'centralnotice-templates' => 'Πρότυπα',
	'centralnotice-notices' => 'Ανακοινώσεις',
	'centralnotice-edit-template' => 'Επεξεργασία προτύπου',
	'centralnotice-message' => 'Μήνυμα',
);

/** Esperanto (Esperanto)
 * @author Yekrats
 */
$messages['eo'] = array(
	'centralnotice' => 'Administranto de centrala notico',
	'noticetemplate' => 'Ŝablono por centrala notico',
	'centralnotice-desc' => 'Aldonas centralan noticon por la vikio',
	'centralnotice-summary' => 'Ĉi tiu modulo permesas al vi redakti viajn aktualajn centralajn noticojn.
Ĝi ankaŭ estas uzable por aldoni aŭ forigi malfreŝajn noticojn.',
	'centralnotice-notice-name' => 'Notica nomo',
	'centralnotice-end-date' => 'Fina dato',
	'centralnotice-enabled' => 'Ŝaltita',
	'centralnotice-modify' => 'Enigi',
	'centralnotice-remove' => 'Forigi',
	'centralnotice-translate-heading' => 'Traduko por $1',
	'centralnotice-manage' => 'Administri centralan noticon',
	'centralnotice-add' => 'Aldoni',
	'centralnotice-add-notice' => 'Aldoni noticon',
	'centralnotice-add-template' => 'Aldoni ŝablonon',
	'centralnotice-english' => 'Angla',
	'centralnotice-template-name' => 'Ŝablona nomo',
	'centralnotice-templates' => 'Ŝablonoj',
	'centralnotice-weight' => 'Graveco',
	'centralnotice-locked' => 'Ŝlosita',
	'centralnotice-notices' => 'Noticoj',
	'centralnotice-notice-exists' => 'Notico jam ekzistas.
Ne aldonante',
	'centralnotice-template-exists' => 'Ŝablono jam ekzistas.
Ne aldonante',
	'centralnotice-notice-doesnt-exist' => 'Notico ne ekzistas.
Neniu forigi',
	'centralnotice-template-still-bound' => 'Ŝablono ankoraŭ estas ligita al notico.
Ne forigante.',
	'centralnotice-day' => 'Tago',
	'centralnotice-year' => 'Jaro',
	'centralnotice-month' => 'Monato',
	'centralnotice-hours' => 'Horo',
	'centralnotice-min' => 'Minuto',
	'centralnotice-project-lang' => 'Projekta lingvo',
	'centralnotice-project-name' => 'Projekta nomo',
	'centralnotice-start-date' => 'Komenca dato',
	'centralnotice-start-time' => 'Komenca tempo (UTC)',
	'centralnotice-assigned-templates' => 'Asignitaj ŝablonoj',
	'centralnotice-no-templates' => 'Neniuj ŝablonoj estis trovitaj.
Aldonu iujn!',
	'centralnotice-no-templates-assigned' => 'Neniuj ŝablonoj estas asignitaj al notico.
Aldonu iujn!',
	'centralnotice-available-templates' => 'Utileblaj ŝablonoj',
	'centralnotice-preview-template' => 'Antaŭrigardi ŝablonon',
	'centralnotice-start-hour' => 'Komenca tempo',
	'centralnotice-change-lang' => 'Ŝanĝi traduklingvon',
	'centralnotice-weights' => 'Pezoj',
	'centralnotice-notice-is-locked' => 'Notico estas ŝlosita.
Ne forigante',
	'centralnotice-null-string' => 'Ne povas aldoni nulan signoĉenon.
Ne aldonante.',
	'centralnotice-confirm-delete' => 'Ĉu vi certas ke vi volas forigi ĉi tiun aĵon?
Ĉi tiu ago ne estos malfarebla.',
	'centralnotice-no-notices-exist' => 'Neniuj noticoj ekzistas.
Afiŝu noticon suben',
	'centralnotice-number-uses' => 'Uzoj',
	'centralnotice-edit-template' => 'Redakti ŝablonojn',
	'centralnotice-message' => 'Mesaĝo',
	'centralnotice-message-not-set' => 'Mesaĝo ne estis ŝaltita',
	'centralnotice-clone' => 'Kloni',
	'centralnotice-clone-notice' => 'Krei duplikaton de la ŝablono',
	'centralnotice-preview-all-template-translations' => 'Antaŭvidi ĉiujn haveblajn tradukojn de ŝablono',
	'right-centralnotice_admin_rights' => 'Administri centralajn noticojn',
	'right-centralnotice_translate_rights' => 'Traduki centralajn noticojn',
	'action-centralnotice_admin_rights' => 'administri centralajn noticojn',
	'action-centralnotice_translate_rights' => 'traduki centralajn noticojn',
);

/** Spanish (Español)
 * @author Muro de Aguas
 */
$messages['es'] = array(
	'centralnotice-desc' => 'Añade un mensaje central común a todos los proyectos.',
);

/** Persian (فارسی)
 * @author Huji
 * @author Komeil 4life
 */
$messages['fa'] = array(
	'centralnotice' => 'مدیر اعلان متمرکز',
	'noticetemplate' => 'الگوی اعلان متمرکز',
	'centralnotice-desc' => 'یک اعلان متمرکز می‌افزاید',
	'centralnotice-summary' => 'این ابزار به شما اجازه می‌دهد که اعلانات متمرکز خود را ویرایش کنید.
از آن می‌توان برای افزودن یا برداشتن اعلان‌های قبلی نیز استفاده کرد.',
	'centralnotice-query' => 'تغییر اعلان‌های اخیر',
	'centralnotice-notice-name' => 'نام اعلان',
	'centralnotice-end-date' => 'تاریخ پایان',
	'centralnotice-enabled' => 'فعال',
	'centralnotice-modify' => 'ارسال',
	'centralnotice-preview' => 'نمایش',
	'centralnotice-add-new' => 'افزودن یک اعلان متمرکز جدید',
	'centralnotice-remove' => 'حذف',
	'centralnotice-translate-heading' => 'ترجمه از $1',
	'centralnotice-manage' => 'مدیریت اعلان متمرکز',
	'centralnotice-add' => 'اضافه کردن',
	'centralnotice-add-notice' => 'اضافه کردن خبر',
	'centralnotice-add-template' => 'اضافه کردن الگو',
	'centralnotice-show-notices' => 'نمایش اعلان‌ها',
	'centralnotice-list-templates' => 'فهرست الگوها',
	'centralnotice-translations' => 'ترجمه‌ها',
	'centralnotice-translate-to' => 'ترجمه به',
	'centralnotice-translate' => 'ترجمه کردن',
	'centralnotice-english' => 'انگلیسی',
	'centralnotice-template-name' => 'نام الگو',
	'centralnotice-templates' => 'الگوها',
	'centralnotice-weight' => 'وزن',
	'centralnotice-locked' => 'قفل شده',
	'centralnotice-notices' => 'اعلانات',
	'centralnotice-notice-exists' => 'اعلان از قبل وجود دارد.
افزوده نشد',
	'centralnotice-template-exists' => 'الگو از قبل وجود دارد.
افزوده نشد',
	'centralnotice-notice-doesnt-exist' => 'اعلان وجود ندارد.
چیزی برای حذف وجود ندارد',
	'centralnotice-template-still-bound' => 'الگو هنوز در اتصال با یک اعلان است.
حذف نشد',
	'centralnotice-template-body' => 'بدنه قالب:',
	'centralnotice-day' => 'روز',
	'centralnotice-year' => 'سال',
	'centralnotice-month' => 'ماه',
	'centralnotice-hours' => 'ساعت',
	'centralnotice-min' => 'دقیقه',
	'centralnotice-project-lang' => 'زبان پروژه',
	'centralnotice-project-name' => 'نام پروژه',
	'centralnotice-start-date' => 'تاریخ آغاز',
	'centralnotice-start-time' => 'زمان آغاز',
	'centralnotice-assigned-templates' => 'الگوهای متصل شده',
	'centralnotice-no-templates' => 'در این سیستم هیچ الگویی نیست. 
چندتا بسازید.',
	'centralnotice-no-templates-assigned' => 'الگویی به این اعلان متصل نیست.
اضافه کنید!',
	'centralnotice-available-templates' => 'الگوهای موجود',
	'centralnotice-template-already-exists' => 'الگو از قبل به اعلان گره خورده است.
افزوده نشد',
	'centralnotice-preview-template' => 'الگو نمایش',
	'centralnotice-start-hour' => 'زمان شروع',
	'centralnotice-change-lang' => 'تغییر زبان ترجمه',
	'centralnotice-weights' => 'وزن‌ها',
	'centralnotice-notice-is-locked' => 'اعلان قفل شده‌است.
افزوده نشد',
	'centralnotice-overlap' => 'اعلان با زمان یک اعلان دیگر تداخل دارد.
افزوده نشد',
	'centralnotice-invalid-date-range' => 'بازهٔ زمانی غیر مجاز.
به روز نشد',
	'centralnotice-null-string' => 'رشتهٔ خالی را نمی‌توان افزود.
افزوده نشد',
	'centralnotice-confirm-delete' => 'آیا مطمئن هستید که می‌خواهید این گزینه را حذف کنید؟
این عمل غیر قابل بازگشت خواهد بود.',
	'centralnotice-no-notices-exist' => 'اعلانی وجود ندارد.
یکی اضافه کنید',
	'centralnotice-no-templates-translate' => 'الگویی وجود ندارد که ترجمه‌اش را ویرایش کنید',
	'centralnotice-number-uses' => 'کاربردها',
	'centralnotice-edit-template' => 'الگو ویرایش',
	'centralnotice-message' => 'پیام',
	'centralnotice-message-not-set' => 'پیغام تنظیم نشده',
	'right-centralnotice_admin_rights' => 'مدیریت اعلان‌های متمرکز',
	'right-centralnotice_translate_rights' => 'ترجمهٔ اعلان‌های متمرکز',
	'action-centralnotice_admin_rights' => 'مدیریت اعلان‌های متمرکز',
	'action-centralnotice_translate_rights' => 'ترجمهٔ اعلان‌های متمرکز',
);

/** Finnish (Suomi)
 * @author Crt
 */
$messages['fi'] = array(
	'centralnotice-desc' => 'Mahdollistaa yleisen sivutiedotteen lisäämisen.',
);

/** French (Français)
 * @author Grondin
 * @author IAlex
 * @author McDutchie
 * @author Meithal
 * @author Sherbrooke
 */
$messages['fr'] = array(
	'centralnotice' => 'Administration des avis centraux',
	'noticetemplate' => 'Modèles des avis centraux',
	'centralnotice-desc' => 'Ajoute un sitenotice central',
	'centralnotice-summary' => 'Ce module vous permet de modifier vos paramètrres des notifications centrales.',
	'centralnotice-notice-name' => "Nom de l'avis",
	'centralnotice-end-date' => 'Date de fin',
	'centralnotice-enabled' => 'Activé',
	'centralnotice-modify' => 'Soumettre',
	'centralnotice-remove' => 'Supprimer',
	'centralnotice-translate-heading' => 'Traduction de $1',
	'centralnotice-manage' => 'Gérer les avis centraux',
	'centralnotice-add' => 'Ajouter',
	'centralnotice-add-notice' => 'Ajouter un avis',
	'centralnotice-add-template' => 'Ajouter un modèle',
	'centralnotice-english' => 'Anglais',
	'centralnotice-template-name' => 'Nom du modèle',
	'centralnotice-templates' => 'Modèles',
	'centralnotice-weight' => 'Poids',
	'centralnotice-locked' => 'Verrouillé',
	'centralnotice-notices' => 'Avis',
	'centralnotice-notice-exists' => "L'avis existe déjà.
Il n'a pas été ajouté.",
	'centralnotice-template-exists' => "Le modèle existe déjà.
Il n'a pas été ajouté.",
	'centralnotice-notice-doesnt-exist' => "L'avis n'existe pas.
Il n'y a rien à supprimer.",
	'centralnotice-template-still-bound' => "Le modèle est encore relié à un avis.
Il n'a pas été supprimé.",
	'centralnotice-day' => 'Jour',
	'centralnotice-year' => 'Année',
	'centralnotice-month' => 'Mois',
	'centralnotice-hours' => 'Heure',
	'centralnotice-min' => 'Minute',
	'centralnotice-project-lang' => 'Langue du projet',
	'centralnotice-project-name' => 'Nom du projet',
	'centralnotice-start-date' => 'Date de début',
	'centralnotice-start-time' => 'Heure de début (UTC)',
	'centralnotice-assigned-templates' => 'Modèles assignés',
	'centralnotice-no-templates' => 'Il n’y a pas de modèle dans le système.',
	'centralnotice-no-templates-assigned' => "Aucun modèle assigné à l'avis.
Ajoutez-en un !",
	'centralnotice-available-templates' => 'Modèles disponibles',
	'centralnotice-template-already-exists' => 'Le modèle est déjà attaché à une campagne.
Ne pas ajouter',
	'centralnotice-preview-template' => 'Prévisualisation du modèle',
	'centralnotice-start-hour' => 'Heure de début',
	'centralnotice-change-lang' => 'Modifier la langue de traduction',
	'centralnotice-weights' => 'Poids',
	'centralnotice-notice-is-locked' => "L'avis est verrouillé.
Il n'a pas été supprimé.",
	'centralnotice-invalid-date-range' => 'Tri de date incorrecte.
Ne pas mettre à jour.',
	'centralnotice-null-string' => 'Ne peut ajouter une chaîne nulle.
Ne pas ajouter.',
	'centralnotice-confirm-delete' => 'Êtes vous sûr que vous voulez supprimer cet article ?
Cette action ne pourra plus être récupérée.',
	'centralnotice-no-notices-exist' => 'Aucun avis existe.
Ajoutez-en un en dessous.',
	'centralnotice-number-uses' => 'Utilisateurs',
	'centralnotice-edit-template' => 'Modifier le modèle',
	'centralnotice-message' => 'Message',
	'centralnotice-message-not-set' => 'Message non renseigné',
	'centralnotice-clone' => 'Cloner',
	'centralnotice-clone-notice' => 'Créer une copie de ce modèle',
	'centralnotice-preview-all-template-translations' => 'Prévisualiser toutes les traductions de ce modèle',
	'right-centralnotice_admin_rights' => 'Gérer les notifications centrales',
	'right-centralnotice_translate_rights' => 'Traduire les notifications centrales',
	'action-centralnotice_admin_rights' => 'gérer les avis centraux',
	'action-centralnotice_translate_rights' => 'traduire les avis centraux',
);

/** Franco-Provençal (Arpetan)
 * @author ChrisPtDe
 */
$messages['frp'] = array(
	'centralnotice-desc' => 'Apond un sitenotice centrâl.',
);

/** Galician (Galego)
 * @author Toliño
 */
$messages['gl'] = array(
	'centralnotice' => 'Administración do aviso central',
	'noticetemplate' => 'Modelo do aviso central',
	'centralnotice-desc' => 'Engade un aviso central',
	'centralnotice-query' => 'Modificar os avisos actuais',
	'centralnotice-notice-name' => 'Nome do aviso',
	'centralnotice-end-date' => 'Data da fin',
	'centralnotice-enabled' => 'Permitido',
	'centralnotice-modify' => 'Enviar',
	'centralnotice-preview' => 'Vista previa',
	'centralnotice-add-new' => 'Engadir un novo aviso central',
	'centralnotice-remove' => 'Eliminar',
	'centralnotice-translate-heading' => 'Traducións de "$1"',
	'centralnotice-manage' => 'Xestionar o aviso central',
	'centralnotice-add' => 'Engadir',
	'centralnotice-add-notice' => 'Engadir un aviso',
	'centralnotice-add-template' => 'Engadir un modelo',
	'centralnotice-show-notices' => 'Amosar os avisos',
	'centralnotice-list-templates' => 'Listar os modelos',
	'centralnotice-translations' => 'Traducións',
	'centralnotice-translate-to' => 'Traducir ao',
	'centralnotice-translate' => 'Traducir',
	'centralnotice-english' => 'inglés',
	'centralnotice-template-name' => 'Nome do modelo',
	'centralnotice-templates' => 'Modelos',
	'centralnotice-weight' => 'Peso',
	'centralnotice-locked' => 'Bloqueado',
	'centralnotice-notices' => 'Avisos',
	'centralnotice-notice-exists' => 'O aviso xa existe.
Non se engade',
	'centralnotice-template-exists' => 'O modelo xa existe.
Non se engade',
	'centralnotice-notice-doesnt-exist' => 'O aviso non existe.
Non hai nada que eliminar',
	'centralnotice-template-body' => 'Corpo do modelo:',
	'centralnotice-day' => 'Día',
	'centralnotice-year' => 'Ano',
	'centralnotice-month' => 'Mes',
	'centralnotice-hours' => 'Hora',
	'centralnotice-min' => 'Minuto',
	'centralnotice-project-lang' => 'Lingua do proxecto',
	'centralnotice-project-name' => 'Nome do proxecto',
	'centralnotice-start-date' => 'Data de inicio',
	'centralnotice-start-time' => 'Hora de inicio (UTC)',
	'centralnotice-assigned-templates' => 'Modelos asignados',
	'centralnotice-no-templates' => 'Non se atopou ningún modelo.
Engada algún!',
	'centralnotice-no-templates-assigned' => 'Non hai modelos asignados a avisos.
Engada algún!',
	'centralnotice-available-templates' => 'Modelos dispoñibles',
	'centralnotice-preview-template' => 'Vista previa do modelo',
	'centralnotice-start-hour' => 'Hora de inicio',
	'centralnotice-change-lang' => 'Cambiar a lingua de tradución',
	'centralnotice-weights' => 'Pesos',
	'centralnotice-notice-is-locked' => 'O aviso está bloqueado.
Non se eliminará',
	'centralnotice-invalid-date-range' => 'Rango de datos inválido.
Non se actualizará',
	'centralnotice-confirm-delete' => 'Está seguro de que quere eliminar este elemento?
Esta acción non poderá ser recuperada',
	'centralnotice-no-notices-exist' => 'Non existe ningún aviso.
Engada algún embaixo',
	'centralnotice-number-uses' => 'Usos',
	'centralnotice-edit-template' => 'Editar o modelo',
	'centralnotice-message' => 'Mensaxe',
	'centralnotice-message-not-set' => 'Mensaxe sen fixar',
	'right-centralnotice_admin_rights' => 'Xestionar os avisos centrais',
	'right-centralnotice_translate_rights' => 'Traducir os avisos centrais',
	'action-centralnotice_admin_rights' => 'xestionar os avisos centrais',
	'action-centralnotice_translate_rights' => 'traducir os avisos centrais',
);

/** Ancient Greek (Ἀρχαία ἑλληνικὴ)
 * @author Crazymadlover
 * @author Omnipaedista
 */
$messages['grc'] = array(
	'centralnotice-modify' => 'Ὑποβάλλειν',
	'centralnotice-preview' => 'Προθεωρεῖν',
	'centralnotice-remove' => 'Άφαιρεῖν',
	'centralnotice-add' => 'Προστιθέναι',
	'centralnotice-weight' => 'Βάρος',
	'centralnotice-preview-template' => 'Προθεωρεῖν πρότυπον',
	'centralnotice-weights' => 'Βάρη',
	'centralnotice-number-uses' => 'Χρήσεις',
);

/** Hebrew (עברית)
 * @author Rotem Liss
 */
$messages['he'] = array(
	'centralnotice' => 'ניהול ההודעה המרכזית',
	'noticetemplate' => 'תבנית ההודעה המרכזית',
	'centralnotice-desc' => 'הוספת הודעה בראש הדף משרת מרכזי',
	'centralnotice-summary' => 'מודול זה מאפשר את עריכת ההודעות המרכזיות המותקנות כעת.
ניתן גם להשתמש בו כדי להוסיף או להסיר הודעות ישנות.',
	'centralnotice-query' => 'שינוי ההודעות הקיימות',
	'centralnotice-notice-name' => 'שם ההודעה',
	'centralnotice-end-date' => 'תאריך סיום',
	'centralnotice-enabled' => 'מופעלת',
	'centralnotice-modify' => 'שליחה',
	'centralnotice-preview' => 'תצוגה מקדימה',
	'centralnotice-add-new' => 'הוספת הודעה מרכזית חדשה',
	'centralnotice-remove' => 'הסרה',
	'centralnotice-translate-heading' => 'תרגום של $1',
	'centralnotice-manage' => 'ניהול ההודעה המרכזית',
	'centralnotice-add' => 'הוספה',
	'centralnotice-add-notice' => 'הוספת הודעה',
	'centralnotice-add-template' => 'הוספת תבנית',
	'centralnotice-show-notices' => 'הצגת הודעות',
	'centralnotice-list-templates' => 'רשימת תבניות',
	'centralnotice-translations' => 'תרגומים',
	'centralnotice-translate-to' => 'תרגום ל',
	'centralnotice-translate' => 'תרגום',
	'centralnotice-english' => 'אנגלית',
	'centralnotice-template-name' => 'שם התבנית',
	'centralnotice-templates' => 'תבניות',
	'centralnotice-weight' => 'משקל',
	'centralnotice-locked' => 'נעול',
	'centralnotice-notices' => 'הודעות',
	'centralnotice-notice-exists' => 'ההודעה כבר קיימת.
התוספת לא תבוצע',
	'centralnotice-template-exists' => 'התבנית כבר קיימת.
התוספת לא תבוצע',
	'centralnotice-notice-doesnt-exist' => 'ההודעה אינה קיימת.
אין מה להסיר',
	'centralnotice-template-still-bound' => 'התבנית עדיין מקושרת להודעה.
ההסרה לא תבוצע.',
	'centralnotice-template-body' => 'גוף ההודעה:',
	'centralnotice-day' => 'יום',
	'centralnotice-year' => 'שנה',
	'centralnotice-month' => 'חודש',
	'centralnotice-hours' => 'שעה',
	'centralnotice-min' => 'דקה',
	'centralnotice-project-lang' => 'שפת המיזם',
	'centralnotice-project-name' => 'שם המיזם',
	'centralnotice-start-date' => 'תאריך ההתחלה',
	'centralnotice-start-time' => 'שעת ההתחלה (UTC)',
	'centralnotice-assigned-templates' => 'תבניות מקושרות',
	'centralnotice-no-templates' => 'לא נמצאו תבניות.
הוסיפו כמה!',
	'centralnotice-no-templates-assigned' => 'אין תבניות המקושרות להודעה.
הוסיפו כמה!',
	'centralnotice-available-templates' => 'תבניות זמינות',
	'centralnotice-template-already-exists' => 'התבנית כבר קשורה להודעה.
התוספת לא תבוצע',
	'centralnotice-preview-template' => 'תצוגה מקדימה של התבנית',
	'centralnotice-start-hour' => 'זמן התחלה',
	'centralnotice-change-lang' => 'שינוי שפת התרגום',
	'centralnotice-weights' => 'משקלים',
	'centralnotice-notice-is-locked' => 'ההודעה נעולה.
היא לא תוסר',
	'centralnotice-overlap' => 'ההודעה מתנגשת עם הזמן של הודעה אחרת.
התוספת לא תבוצע',
	'centralnotice-invalid-date-range' => 'טווח תאריכים בלתי תקין.
העדכון לא יבוצע',
	'centralnotice-null-string' => 'לא ניתן להוסיף מחרוזת ריקה.
התוספת לא תבוצע',
	'centralnotice-confirm-delete' => 'האם אתם בטוחים שברצונכם למחוק פריט זה?
אין אפשרות לבטל פעולה זו.',
	'centralnotice-no-notices-exist' => 'אין עדיין הודעות.
הוסיפו אחת למטה',
	'centralnotice-no-templates-translate' => 'אין תבניות כדי לערוך את התרגומים שלהן',
	'centralnotice-number-uses' => 'משתמשת ב',
	'centralnotice-edit-template' => 'עריכת התבנית',
	'centralnotice-message' => 'הודעה',
	'centralnotice-message-not-set' => 'לא הוגדרה הודעה',
	'centralnotice-clone' => 'שכפול',
	'centralnotice-clone-notice' => 'יצירת עותק של התבנית',
	'centralnotice-preview-all-template-translations' => 'תצוגה מקדימה של כל התרגומים בתבנית',
	'right-centralnotice_admin_rights' => 'ניהול הודעת מרכזיות',
	'right-centralnotice_translate_rights' => 'תרגום הודעות מרכזיות',
	'action-centralnotice_admin_rights' => 'לנהל הודעות מרכזיות',
	'action-centralnotice_translate_rights' => 'לתרגם הודעות מרכזיות',
);

/** Hindi (हिन्दी)
 * @author Kaustubh
 */
$messages['hi'] = array(
	'centralnotice-desc' => 'सेंट्रल साईटनोटिस बढ़ायें',
);

/** Croatian (Hrvatski)
 * @author Dalibor Bosits
 */
$messages['hr'] = array(
	'centralnotice' => 'Administracija središnjih obavijesti',
	'noticetemplate' => 'Predložak središnje obavijesti',
	'centralnotice-desc' => 'Dodaje središnju obavijest za projekt',
	'centralnotice-summary' => 'Ova stranica vam omogućava uređivanje trenutačno postavljenih središnjih obavijesti.
Može biti korištena i za dodavanje ili uklanjanje zastarjelih obavijesti.',
	'centralnotice-query' => 'Promijeni trenutačne obavijesti',
	'centralnotice-notice-name' => 'Naziv obavijesti',
	'centralnotice-end-date' => 'Završni datum',
	'centralnotice-enabled' => 'Omogućeno',
	'centralnotice-modify' => 'Postavi',
	'centralnotice-preview' => 'Pregledaj',
	'centralnotice-add-new' => 'Dodaj novu središnju obavijest',
	'centralnotice-remove' => 'Ukloni',
	'centralnotice-translate-heading' => 'Prijevod za $1',
	'centralnotice-manage' => 'Uredi središnje obavijesti',
	'centralnotice-add' => 'Dodaj',
	'centralnotice-add-notice' => 'Dodaj obavijest',
	'centralnotice-add-template' => 'Dodaj predložak',
	'centralnotice-show-notices' => 'Pokaži obavijesti',
	'centralnotice-list-templates' => 'Popis predložaka',
	'centralnotice-translations' => 'Prijevodi',
	'centralnotice-translate-to' => 'Prevedi na',
	'centralnotice-translate' => 'Prevedi',
	'centralnotice-english' => 'Engleski',
	'centralnotice-template-name' => 'Naziv predloška',
	'centralnotice-templates' => 'Predlošci',
	'centralnotice-weight' => 'Težina',
	'centralnotice-locked' => 'Zaključano',
	'centralnotice-notices' => 'Obavijesti',
	'centralnotice-notice-exists' => 'Obavijest već postoji.
Nije dodano',
	'centralnotice-template-exists' => 'Predložak već postoji.
Nije dodano',
	'centralnotice-notice-doesnt-exist' => 'Obavijest ne postoji.
Ništa nije uklonjeno',
	'centralnotice-template-still-bound' => 'Predložak je još uvijek vezan uz obavijest.
Nije uklonjeno.',
	'centralnotice-template-body' => 'Sadržaj predloška:',
	'centralnotice-day' => 'Dan',
	'centralnotice-year' => 'Godina',
	'centralnotice-month' => 'Mjesec',
	'centralnotice-hours' => 'Sat',
	'centralnotice-min' => 'Minuta',
	'centralnotice-project-lang' => 'Jezik projekta',
	'centralnotice-project-name' => 'Naziv projekta',
	'centralnotice-start-date' => 'Početni datum',
	'centralnotice-start-time' => 'Početno vrijeme (UTC)',
	'centralnotice-assigned-templates' => 'Dodijeljeni predlošci',
	'centralnotice-no-templates' => 'Nije pronađen ni jedan predložak.
Dodaj jedan!',
	'centralnotice-no-templates-assigned' => 'Nijedan predložak nije dodijeljen obavijesti.
Dodaj jedan!',
	'centralnotice-available-templates' => 'Dostupni predlošci',
	'centralnotice-template-already-exists' => 'Predložak je već vezan uz kampanju.
Nije dodano',
	'centralnotice-preview-template' => 'Pregledaj predložak',
	'centralnotice-start-hour' => 'Početno vrijeme',
	'centralnotice-change-lang' => 'Promijeni jezik prijevoda',
	'centralnotice-weights' => 'Težine',
	'centralnotice-notice-is-locked' => 'Obavijest je zaključana.
Nije uklonjeno',
	'centralnotice-overlap' => 'Obavijest se u vremenu preklapa s drugom obaviješću.
Nije dodana',
	'centralnotice-invalid-date-range' => 'Nevaljan opseg datuma.
Nije ažurirano',
	'centralnotice-null-string' => 'Nulta vrijednost se ne može dodati.
Nije dodano',
	'centralnotice-confirm-delete' => 'Jeste li sigurni da želite ovo obrisati?
Ova akcija se neće moći poništiti.',
	'centralnotice-no-notices-exist' => 'Ne postoji ni jedna obavijest.
Dodajte jednu ispod',
	'centralnotice-no-templates-translate' => 'Ne postoje predlošci za prevođenje',
	'centralnotice-number-uses' => 'Koristi',
	'centralnotice-edit-template' => 'Uredi predložak',
	'centralnotice-message' => 'Poruka',
	'centralnotice-message-not-set' => 'Poruka nije postavljena',
	'right-centralnotice_admin_rights' => 'Uređivanje središnjih obavijesti',
	'right-centralnotice_translate_rights' => 'Prevođenje središnjih obavijesti',
	'action-centralnotice_admin_rights' => 'uređivanje središnjih obavijesti',
	'action-centralnotice_translate_rights' => 'prevođenje središnjih obavijesti',
);

/** Upper Sorbian (Hornjoserbsce)
 * @author Michawiki
 */
$messages['hsb'] = array(
	'centralnotice-desc' => 'Přidawa centralnu bóčnu zdźělenku',
);

/** Interlingua (Interlingua)
 * @author McDutchie
 */
$messages['ia'] = array(
	'centralnotice' => 'Administration de avisos central',
	'noticetemplate' => 'Patrono de avisos central',
	'centralnotice-desc' => 'Adde un aviso de sito central',
	'centralnotice-summary' => 'Iste modulo permitte modificar le avisos central actualmente configurate.
Illo pote tamben esser usate pro adder o remover avisos ancian.',
	'centralnotice-query' => 'Modificar avisos actual',
	'centralnotice-notice-name' => 'Nomine del aviso',
	'centralnotice-end-date' => 'Data de fin',
	'centralnotice-enabled' => 'Active',
	'centralnotice-modify' => 'Submitter',
	'centralnotice-preview' => 'Previsualisar',
	'centralnotice-add-new' => 'Adder un nove aviso central',
	'centralnotice-remove' => 'Remover',
	'centralnotice-translate-heading' => 'Traduction de $1',
	'centralnotice-manage' => 'Gerer aviso central',
	'centralnotice-add' => 'Adder',
	'centralnotice-add-notice' => 'Adder un aviso',
	'centralnotice-add-template' => 'Adder un patrono',
	'centralnotice-show-notices' => 'Monstrar avisos',
	'centralnotice-list-templates' => 'Listar patronos',
	'centralnotice-translations' => 'Traductiones',
	'centralnotice-translate-to' => 'Traducer in',
	'centralnotice-translate' => 'Traducer',
	'centralnotice-english' => 'Anglese',
	'centralnotice-template-name' => 'Nomine del patrono',
	'centralnotice-templates' => 'Patronos',
	'centralnotice-weight' => 'Peso',
	'centralnotice-locked' => 'Serrate',
	'centralnotice-notices' => 'Avisos',
	'centralnotice-notice-exists' => 'Aviso existe ja.
Non es addite',
	'centralnotice-template-exists' => 'Patrono existe ja.
Non es addite',
	'centralnotice-notice-doesnt-exist' => 'Aviso non existe.
Nihil a remover',
	'centralnotice-template-still-bound' => 'Patrono es ancora ligate a un aviso.
Non es removite.',
	'centralnotice-template-body' => 'Corpore del patrono:',
	'centralnotice-day' => 'Die',
	'centralnotice-year' => 'Anno',
	'centralnotice-month' => 'Mense',
	'centralnotice-hours' => 'Hora',
	'centralnotice-min' => 'Minuta',
	'centralnotice-project-lang' => 'Lingua del projecto',
	'centralnotice-project-name' => 'Nomine del projecto',
	'centralnotice-start-date' => 'Data de initio',
	'centralnotice-start-time' => 'Tempore de initio (UTC)',
	'centralnotice-assigned-templates' => 'Patronos assignate',
	'centralnotice-no-templates' => 'Nulle patrono trovate.
Adde alcunes!',
	'centralnotice-no-templates-assigned' => 'Nulle patronos assignate al aviso.
Adde alcunes!',
	'centralnotice-available-templates' => 'Patronos disponibile',
	'centralnotice-template-already-exists' => 'Le patrono es ja ligate a un campania.
Non es addite',
	'centralnotice-preview-template' => 'Previsualisar patrono',
	'centralnotice-start-hour' => 'Tempore de initio',
	'centralnotice-change-lang' => 'Cambiar lingua de traduction',
	'centralnotice-weights' => 'Pesos',
	'centralnotice-notice-is-locked' => 'Aviso es serrate.
Non es removite',
	'centralnotice-overlap' => 'Aviso imbrica in le tempore de un altere aviso.
Non es addite',
	'centralnotice-invalid-date-range' => 'Intervallo incorrecte de datas.
Non es actualisate',
	'centralnotice-null-string' => 'Non pote adder un catena de characteres vacue.
Non es addite',
	'centralnotice-confirm-delete' => 'Es tu secur que tu vole deler iste articulo?
Iste action essera irrecuperabile.',
	'centralnotice-no-notices-exist' => 'Nulle aviso existe.
Adde un infra',
	'centralnotice-no-templates-translate' => 'Non existe alcun patrono a traducer',
	'centralnotice-number-uses' => 'Usos',
	'centralnotice-edit-template' => 'Modificar patrono',
	'centralnotice-message' => 'Message',
	'centralnotice-message-not-set' => 'Message non definite',
	'right-centralnotice_admin_rights' => 'Gerer avisos central',
	'right-centralnotice_translate_rights' => 'Traducer avisos central',
	'action-centralnotice_admin_rights' => 'gerer avisos central',
	'action-centralnotice_translate_rights' => 'traducer avisos central',
);

/** Indonesian (Bahasa Indonesia)
 * @author IvanLanin
 */
$messages['id'] = array(
	'centralnotice-desc' => 'Menambahkan pengumuman situs terpusat',
);

/** Italian (Italiano)
 * @author BrokenArrow
 * @author Darth Kule
 * @author Melos
 */
$messages['it'] = array(
	'centralnotice' => 'Gestione avviso centralizzato',
	'centralnotice-desc' => 'Aggiunge un avviso centralizzato a inizio pagina (sitenotice)',
	'centralnotice-summary' => 'Questo modulo permette di modificare gli avvisi centralizzati. Puoi essere inoltre usato per aggiungere o rimuovere vecchi avvisi.',
	'centralnotice-query' => 'Modifica avvisi attuali',
	'centralnotice-notice-name' => "Nome dell'avviso",
	'centralnotice-end-date' => 'Data di fine',
	'centralnotice-enabled' => 'Attivato',
	'centralnotice-modify' => 'Invia',
	'centralnotice-preview' => 'Anteprima',
	'centralnotice-add-new' => 'Aggiungi un nuovo avviso centralizzato',
	'centralnotice-remove' => 'Rimuovi',
	'centralnotice-translate-heading' => 'Traduzione di $1',
	'centralnotice-manage' => 'Gestione avvisi centralizzati',
	'centralnotice-add' => 'Aggiungi',
	'centralnotice-add-notice' => 'Aggiungi un avviso',
	'centralnotice-add-template' => 'Aggiungi un template',
	'centralnotice-show-notices' => 'Mostra avvisi',
	'centralnotice-translations' => 'Traduzioni',
	'centralnotice-translate-to' => 'Traduci in',
	'centralnotice-translate' => 'Traduci',
	'centralnotice-english' => 'Inglese',
	'centralnotice-template-name' => 'Nome template',
	'centralnotice-templates' => 'Template',
	'centralnotice-locked' => 'Bloccato',
	'centralnotice-notices' => 'Avvisi',
	'centralnotice-notice-exists' => "Avviso già esistente. L'avviso non è stato aggiunto",
	'centralnotice-template-exists' => 'Template già esistente. Il template non è stato aggiunto',
	'centralnotice-template-body' => 'Corpo del template:',
	'centralnotice-day' => 'Giorno',
	'centralnotice-year' => 'Anno',
	'centralnotice-month' => 'Mese',
	'centralnotice-hours' => 'Ora',
	'centralnotice-min' => 'Minuto',
	'centralnotice-project-lang' => 'Lingua progetto',
	'centralnotice-project-name' => 'Nome progetto',
	'centralnotice-start-date' => 'Data di inizio',
	'centralnotice-start-time' => 'Ora di inizio (UTC)',
	'centralnotice-assigned-templates' => 'Template assegnati',
	'centralnotice-no-templates' => 'Nessun template trovato. Aggiungine qualcuno!',
	'centralnotice-available-templates' => 'Template disponibili',
	'centralnotice-preview-template' => 'Anteprima template',
	'centralnotice-start-hour' => 'Ora di inizio',
	'centralnotice-change-lang' => 'Cambia lingua della traduzione',
	'centralnotice-notice-is-locked' => "L'avviso è bloccato. Avviso non rimosso",
	'centralnotice-confirm-delete' => "Sei veramente sicuro di voler cancellare questo elemento? L'azione non è reversibile.",
	'centralnotice-no-notices-exist' => 'Non esiste alcun avviso. Aggiungine uno di seguito',
	'centralnotice-number-uses' => 'Usi',
	'centralnotice-edit-template' => 'Modifica template',
	'centralnotice-message' => 'Messaggio',
	'right-centralnotice_admin_rights' => 'Gestisce gli avvisi centralizzati',
	'right-centralnotice_translate_rights' => 'Traduce avvisi centralizzati',
	'action-centralnotice_admin_rights' => 'gestire gli avvisi centralizzati',
	'action-centralnotice_translate_rights' => 'tradurre avvisi centralizzati',
);

/** Japanese (日本語)
 * @author Aotake
 * @author JtFuruhata
 */
$messages['ja'] = array(
	'centralnotice-desc' => '中央上部に表示される、サイトからのお知らせを追加する',
	'centralnotice-modify' => '投稿',
	'centralnotice-remove' => '除去',
	'centralnotice-translate-heading' => '$1の翻訳',
	'centralnotice-manage' => '中央管理のお知らせの編集',
	'centralnotice-add' => '追加',
	'centralnotice-add-notice' => 'お知らせを追加',
	'centralnotice-add-template' => 'テンプレートを追加',
	'centralnotice-english' => '英語',
	'centralnotice-template-name' => 'テンプレート名',
	'centralnotice-templates' => 'テンプレート',
	'centralnotice-weight' => '重さ',
	'centralnotice-locked' => 'ロック中',
	'centralnotice-notices' => 'お知らせ',
	'centralnotice-notice-exists' => '同じ名前のお知らせがすでに存在します。追加できませんでした。',
	'centralnotice-template-exists' => '同じ名前のテンプレートがすでに存在します。追加できませんでした。',
	'centralnotice-notice-doesnt-exist' => 'その名前のお知らせは存在しません。除去できませんでした。',
	'centralnotice-template-still-bound' => 'そのテンプレートはまだお知らせに使用されています。除去できませんでした。',
	'centralnotice-day' => '日',
	'centralnotice-year' => '年',
	'centralnotice-month' => '月',
	'centralnotice-hours' => '時',
	'centralnotice-min' => '分',
	'centralnotice-project-lang' => 'プロジェクト言語',
	'centralnotice-project-name' => 'プロジェクト名',
	'centralnotice-start-date' => '開始日',
	'centralnotice-start-time' => '開始時間 (UTC)',
	'right-centralnotice_admin_rights' => '中央管理のお知らせの編集',
	'right-centralnotice_translate_rights' => '中央管理のお知らせの翻訳',
	'action-centralnotice_admin_rights' => '中央管理のお知らせの編集',
	'action-centralnotice_translate_rights' => '中央管理のお知らせの翻訳',
);

/** Jutish (Jysk)
 * @author Huslåke
 */
$messages['jut'] = array(
	'centralnotice-desc' => "Tilføje'n sentrål sitenotice",
);

/** Javanese (Basa Jawa)
 * @author Meursault2004
 * @author Pras
 */
$messages['jv'] = array(
	'centralnotice' => "Admin cathetan pusat (''central notice'')",
	'noticetemplate' => "Cithakan cathetan pusat (''central notice'')",
	'centralnotice-desc' => 'Nambahaké wara-wara situs punjer',
	'centralnotice-summary' => "Modul iki kanggo nyunting tatanan cathetan pusat (''central notice'') sing ana.
Iki uga bisa kanggo nambah utawa mbuwang cathetan/pangumuman lawas.",
	'centralnotice-notice-name' => 'Jeneng cathetan/pangumuman',
	'centralnotice-end-date' => 'Tanggal dipungkasi',
	'centralnotice-enabled' => 'Diaktifaké',
	'centralnotice-modify' => 'Kirim',
	'centralnotice-remove' => 'Buwang/busak',
	'centralnotice-translate-heading' => 'Terjemahan saka $1',
	'centralnotice-manage' => "Tata cathetan pusat (''central notice'')",
	'centralnotice-add' => 'Tambahaké',
	'centralnotice-add-notice' => 'Tambahaké cathetan',
	'centralnotice-add-template' => 'Tambahaké cithakan',
	'centralnotice-english' => 'Basa Inggris',
	'centralnotice-template-name' => 'Jeneng cithakan',
	'centralnotice-templates' => 'Cithakan',
	'centralnotice-weight' => 'Bobot',
	'centralnotice-locked' => 'Kakunci',
	'centralnotice-notices' => 'Cathetan',
	'centralnotice-notice-exists' => 'Cathetan wis ana.
Dudu panambahan',
	'centralnotice-template-exists' => 'Cithakan wis ana.
Dudu panambahan',
	'centralnotice-notice-doesnt-exist' => 'Cathetan ora ana.
Ora ana sing perlu dibusak',
	'centralnotice-template-still-bound' => 'Cithakan isih diwatesi déning cathetan.
Ora bisa mbusak.',
	'centralnotice-day' => 'Dina',
	'centralnotice-year' => 'Taun',
	'centralnotice-month' => 'Sasi',
	'centralnotice-hours' => 'Jam',
	'centralnotice-min' => 'Menit',
	'centralnotice-project-lang' => 'Basa Proyèk',
	'centralnotice-project-name' => 'Jeneng proyèk',
	'centralnotice-start-date' => 'Tanggal diwiwiti',
	'centralnotice-start-time' => 'Wektu diwiwiti (UTC)',
	'centralnotice-assigned-templates' => 'Cithakan-cithakan sing ditetepaké',
	'centralnotice-no-templates' => 'Ora ana cithakan.
Gawénen!',
	'centralnotice-no-templates-assigned' => "Durung ana cithakan kanggo cathetan/pangumuman (''notice'').
Gawénen!",
	'centralnotice-available-templates' => 'Cithakan-cithakan sing ana',
	'centralnotice-template-already-exists' => "Cithakan isih kagandhèng menyang ''campaing''.
Ora bisa nambah",
	'centralnotice-preview-template' => 'Tampilaké cithakan',
	'centralnotice-start-hour' => 'Wektu diwiwiti',
	'centralnotice-change-lang' => 'Owahi basa terjemahan',
	'centralnotice-weights' => 'Bobot',
	'centralnotice-notice-is-locked' => 'Cathetan dikunci.
Ora bisa mbusak',
	'centralnotice-invalid-date-range' => 'Jangka data ora sah.
Ora bisa ngowahi',
	'centralnotice-null-string' => "Ora bisa nambah ''null string''. Ora bisa nambah",
	'centralnotice-confirm-delete' => 'Panjenengan yakin bakal mbusak item iki?
Tumindak iki bakal ora bisa didandani manèh.',
	'centralnotice-no-notices-exist' => 'Durung ana cathetan.
Tambahaké ing ngisor',
	'centralnotice-number-uses' => 'Guna',
	'centralnotice-edit-template' => 'Sunting cithakan',
	'centralnotice-message' => 'Warta',
	'centralnotice-message-not-set' => 'Warta durung di sèt',
	'centralnotice-clone' => 'Kloning',
	'centralnotice-clone-notice' => "Gawé salinan (''copy'') saka cithakan",
	'centralnotice-preview-all-template-translations' => 'Tampilaké kabèh terjemahan cithakan sing ana',
	'right-centralnotice_admin_rights' => 'Tata cathetan pusat',
	'right-centralnotice_translate_rights' => "Terjemahaké cathetan pusat (''central notices'')",
	'action-centralnotice_admin_rights' => "tata cathetan pusat (''central notices'')",
	'action-centralnotice_translate_rights' => "terjemahaké cathetan pusat (''central notices'')",
);

/** Khmer (ភាសាខ្មែរ)
 * @author Lovekhmer
 */
$messages['km'] = array(
	'centralnotice-modify' => 'ដាក់ស្នើ',
	'centralnotice-preview' => 'មើលជាមុន',
	'centralnotice-remove' => 'ដកចេញ',
	'centralnotice-add' => 'ដាក់បន្ថែម',
	'centralnotice-add-template' => 'បន្ថែមទំព័រគំរូ',
	'centralnotice-translations' => 'ការបកប្រែ',
	'centralnotice-translate' => 'បកប្រែ',
	'centralnotice-english' => 'ភាសាអង់គ្លេស',
	'centralnotice-template-name' => 'ឈ្មោះទំព័រគំរូ',
	'centralnotice-templates' => 'ទំព័រគំរូ',
	'centralnotice-locked' => 'បានចាក់សោ',
	'centralnotice-day' => 'ថ្ងៃ',
	'centralnotice-year' => 'ឆ្នាំ',
	'centralnotice-month' => 'ខែ',
	'centralnotice-hours' => 'ម៉ោង',
	'centralnotice-min' => 'នាទី',
	'centralnotice-project-lang' => 'ភាសាគំរោង',
	'centralnotice-project-name' => 'ឈ្មោះគំរោង',
	'centralnotice-preview-template' => 'មើលទំព័រគំរូជាមុន',
	'centralnotice-edit-template' => 'កែប្រែទំព័រគំរូ',
	'centralnotice-message' => 'សារ',
);

/** Korean (한국어)
 * @author Kwj2772
 */
$messages['ko'] = array(
	'centralnotice-preview' => '미리 보기',
	'centralnotice-translate' => '번역하기',
	'centralnotice-english' => '영어',
	'centralnotice-project-lang' => '프로젝트 언어',
	'centralnotice-project-name' => '프로젝트 이름',
	'centralnotice-start-time' => '시작 시간 (UTC)',
);

/** Ripoarisch (Ripoarisch)
 * @author Purodha
 */
$messages['ksh'] = array(
	'centralnotice' => 'Zentraal Nohreschte verwallde',
	'noticetemplate' => 'Schabloon för zentraal Nohreschte',
	'centralnotice-desc' => "Brengk en zentraale ''sitenotice'' en et wiki",
	'centralnotice-summary' => 'Hee met kanns De de zentraal Nohreschte ändere, die jraad em Wiki opjesaz sen,
ävver och neue dobei donn, un allde fott schmieße.',
	'centralnotice-query' => 'Aktowälle zentraale Nohresch ändere.',
	'centralnotice-notice-name' => 'Dä Nohresch ier Name',
	'centralnotice-end-date' => 'Et Dattum fum Engk',
	'centralnotice-enabled' => 'Aanjeschalldt',
	'centralnotice-modify' => 'Loß Jonn!',
	'centralnotice-preview' => 'Vör-Aansich zeije',
	'centralnotice-add-new' => 'Donn en zentrale Nohresch dobei',
	'centralnotice-remove' => 'Fottnämme',
	'centralnotice-translate-heading' => 'Övversäzong för $1',
	'centralnotice-manage' => 'Zentrale Nohreschte fowallde',
	'centralnotice-add' => 'Dobeidonn',
	'centralnotice-add-notice' => 'En zentrale Nohresch dobei donn',
	'centralnotice-add-template' => 'En Schabloon dobei donn',
	'centralnotice-show-notices' => 'Zentrale Nohreschte zeije',
	'centralnotice-list-templates' => 'Schablone opleßte',
	'centralnotice-translations' => 'Övversäzonge',
	'centralnotice-translate-to' => 'Övversäze noh',
	'centralnotice-translate' => 'Övversäze',
	'centralnotice-english' => 'Englesch',
	'centralnotice-template-name' => 'Dä Schablon iere Name',
	'centralnotice-templates' => 'Schablone',
	'centralnotice-weight' => 'Jeweesch',
	'centralnotice-locked' => 'jespert',
	'centralnotice-notices' => 'zentrale Nohreschte',
	'centralnotice-notice-exists' => 'Di zentrale Nohresch es ald doh.
Nix dobei jedonn.',
	'centralnotice-template-exists' => 'Di Schablon es ald doh.
Nit dobei jedonn.',
	'centralnotice-notice-doesnt-exist' => 'Di zentrale Nohresch es nit doh.
Kam_mer nit fott lohße.',
	'centralnotice-template-still-bound' => 'Di Schablon deit aan ene zentrale Nohresch hange.
Di kam_mer nit fott nämme.',
	'centralnotice-template-body' => 'Dä Tex fun dä Schablon:',
	'centralnotice-day' => 'Daach',
	'centralnotice-year' => 'Johr',
	'centralnotice-month' => 'Moohnd',
	'centralnotice-hours' => 'Shtund',
	'centralnotice-min' => 'Menutt',
	'centralnotice-project-lang' => 'Däm Projäk sing Shprooch',
	'centralnotice-project-name' => 'Däm Projäk singe Name',
	'centralnotice-start-date' => 'Et Annfangsdattum',
	'centralnotice-start-time' => 'De Aanfangszick (UTC)',
	'centralnotice-assigned-templates' => 'Zojedeilte Schablone',
	'centralnotice-no-templates' => 'Mer han kein Schablone.
Kanns ävver welshe dobei don.',
	'centralnotice-start-hour' => 'Uhrzigg fum Aanfang',
	'centralnotice-weights' => 'Jeweeschte',
	'centralnotice-no-notices-exist' => 'Mer han kein Nohreschte.
De kanns ävver welshe dobei don.',
	'centralnotice-number-uses' => 'Jebruch',
	'centralnotice-edit-template' => 'Schablon beärbeide',
	'centralnotice-message' => 'Nohresch',
	'centralnotice-message-not-set' => 'De Nohresch es nit jesaz',
	'right-centralnotice_admin_rights' => 'Zentraal Nohreschte verwallde',
);

/** Luxembourgish (Lëtzebuergesch)
 * @author Robby
 */
$messages['lb'] = array(
	'centralnotice' => 'Administratioun vun der zenraler Notiz',
	'noticetemplate' => 'Schabloun vun der zentraler Notiz',
	'centralnotice-desc' => "Setzt eng zentral 'Sitenotice' derbäi",
	'centralnotice-notice-name' => 'Numm vun der Notiz',
	'centralnotice-end-date' => 'Schlussdatum',
	'centralnotice-enabled' => 'Aktivéiert',
	'centralnotice-modify' => 'Späicheren',
	'centralnotice-remove' => 'Ewechhuelen',
	'centralnotice-translate-heading' => 'Iwwersetzung vu(n) $1',
	'centralnotice-add' => 'Derbäisetzen',
	'centralnotice-add-notice' => 'Eng Notiz derbäisetzen',
	'centralnotice-add-template' => 'Eng Schabloun derbäisetzen',
	'centralnotice-english' => 'Englesch',
	'centralnotice-template-name' => 'Numm vun der Schabloun',
	'centralnotice-templates' => 'Schablounen',
	'centralnotice-weight' => 'Gewiicht',
	'centralnotice-locked' => 'Gespaart',
	'centralnotice-notices' => 'Notizen',
	'centralnotice-notice-exists' => "D'Notiz gëtt et schonn.
Si konnt net derbäigesat ginn.",
	'centralnotice-template-exists' => "D'Schabloun gëtt et schonn.
Et gouf näischt derbäigsat.",
	'centralnotice-notice-doesnt-exist' => "D'notiz gëtt et net.
Et gëtt näischt fir ewechzehuelen.",
	'centralnotice-day' => 'Dag',
	'centralnotice-year' => 'Joer',
	'centralnotice-month' => 'Mount',
	'centralnotice-hours' => 'Stonn',
	'centralnotice-min' => 'Minutt',
	'centralnotice-project-lang' => 'Sprooch vum Projet',
	'centralnotice-project-name' => 'Numm vum Projet',
	'centralnotice-start-date' => 'Ufanksdatum',
	'centralnotice-start-time' => 'Ufankszäit (UTC)',
	'centralnotice-assigned-templates' => 'Zougewise Schablounen',
	'centralnotice-no-templates' => 'Et gëtt keng Schablounen am System',
	'centralnotice-available-templates' => 'Disponibel Schablounen',
	'centralnotice-template-already-exists' => "D'Schabloun ass schonn enger Campagne zougedeelt.
Net derbäisetzen",
	'centralnotice-preview-template' => 'Schabloun weisen ouni ze späicheren',
	'centralnotice-start-hour' => 'Ufankszäit',
	'centralnotice-change-lang' => 'Sprooch vun der Iwwersetzung änneren',
	'centralnotice-weights' => 'Gewiicht',
	'centralnotice-notice-is-locked' => "D'Notiz ass gespaart.
Se kann net ewechgeholl ginn.",
	'centralnotice-invalid-date-range' => 'Ongëltegen Zäitraum.
Gëtt net aktualiséiert.',
	'centralnotice-null-string' => 'Et ass net méiglech näischt derbäizesetzen.
Näischt derbäigesat',
	'centralnotice-confirm-delete' => 'Sidd Dir sécher datt Dir dës Säit läsche wëllt?
Dës Aktioun kann net réckgängeg gemaach ginn.',
	'centralnotice-no-notices-exist' => 'Et gëtt keng Notiz.
Setzt eng hei ënnendrënner bäi.',
	'centralnotice-number-uses' => 'gëtt benotzt',
	'centralnotice-edit-template' => 'Schabloun änneren',
	'centralnotice-message' => 'Message',
	'centralnotice-message-not-set' => 'Message net gepäichert',
	'centralnotice-clone' => 'Eng Kopie maachen',
	'right-centralnotice_translate_rights' => 'Zentral Notizen iwwersetzen',
	'action-centralnotice_translate_rights' => 'Zentral Notiz iwwersetzen',
);

/** Limburgish (Limburgs)
 * @author Matthias
 */
$messages['li'] = array(
	'centralnotice-desc' => "Voegt 'n centrale sitemededeling toe",
);

/** Macedonian (Македонски)
 * @author Brest
 */
$messages['mk'] = array(
	'centralnotice-desc' => 'Додава централизирано известување',
);

/** Malayalam (മലയാളം)
 * @author Shijualex
 */
$messages['ml'] = array(
	'centralnotice-desc' => 'കേന്ദീകൃത സൈറ്റ്നോട്ടീസ് ചേര്‍ക്കുന്നു',
);

/** Marathi (मराठी)
 * @author Mahitgar
 */
$messages['mr'] = array(
	'centralnotice-desc' => 'संकेतस्थळाचा मध्यवर्ती सूचना फलक',
);

/** Malay (Bahasa Melayu)
 * @author Aviator
 */
$messages['ms'] = array(
	'centralnotice' => 'Pentadbiran pemberitahuan pusat',
	'noticetemplate' => 'Templat pemberitahuan pusat',
	'centralnotice-desc' => 'Menambah pemberitahuan pusat',
	'centralnotice-summary' => 'Anda boleh menggunakan modul ini untuk menyunting pemberitahuan pusat yang disediakan. Anda juga boleh menambah atau membuang pemberitahuan yang lama.',
	'centralnotice-notice-name' => 'Nama pemberitahuan',
	'centralnotice-end-date' => 'Tarikh tamat',
	'centralnotice-enabled' => 'Boleh',
	'centralnotice-modify' => 'Serah',
	'centralnotice-remove' => 'Buang',
	'centralnotice-translate-heading' => 'Penterjemahan $1',
	'centralnotice-manage' => 'Urus pemberitahuan pusat',
	'centralnotice-add' => 'Tambah',
	'centralnotice-add-notice' => 'Tambah pemberitahuan',
	'centralnotice-add-template' => 'Tambah templat',
	'centralnotice-english' => 'Bahasa Inggeris',
	'centralnotice-template-name' => 'Nama templat',
	'centralnotice-templates' => 'Templat',
	'centralnotice-locked' => 'Dikunci',
	'centralnotice-notices' => 'Pemberitahuan',
	'centralnotice-notice-exists' => 'Pemberitahuan telah pun wujud dan tidak ditambah.',
	'centralnotice-template-exists' => 'Templat telah pun wujud dan tidak ditambah.',
	'centralnotice-notice-doesnt-exist' => 'Pemberitahuan tidak wujud untuk dibuang.',
	'centralnotice-template-still-bound' => 'Templat masih digunakan untuk pemberitahuan dan tidak dibuang.',
	'centralnotice-day' => 'Hari',
	'centralnotice-year' => 'Tahun',
	'centralnotice-month' => 'Bulan',
	'centralnotice-hours' => 'Jam',
	'centralnotice-min' => 'Minit',
	'centralnotice-project-lang' => 'Bahasa projek',
	'centralnotice-project-name' => 'Nama projek',
	'centralnotice-start-date' => 'Tarikh mula',
	'centralnotice-start-time' => 'Waktu mula (UTC)',
	'centralnotice-no-templates' => 'Tiada templat. Sila cipta templat baru.',
	'centralnotice-available-templates' => 'Templat yang ada',
	'centralnotice-preview-template' => 'Pralihat templat',
	'centralnotice-start-hour' => 'Waktu mula',
	'centralnotice-change-lang' => 'Tukar bahasa terjemahan',
	'centralnotice-notice-is-locked' => 'Pemberitahuan telah dikunci dan tidak boleh dibuang.',
	'centralnotice-invalid-date-range' => 'Julat tarikh tidak sah dan tidak dikemaskinikan.',
	'centralnotice-null-string' => 'Rentetan kosong tidak boleh ditambah.',
	'centralnotice-confirm-delete' => 'Betul anda mahu menghapuskan item ini? Tindakan ini tidak boleh dipulihkan.',
	'centralnotice-edit-template' => 'Sunting templat',
	'centralnotice-message' => 'Pesanan',
	'centralnotice-message-not-set' => 'Pesanan tidak ditetapkan',
	'centralnotice-clone' => 'Salin',
	'centralnotice-clone-notice' => 'Buat salinan templat ini',
	'centralnotice-preview-all-template-translations' => 'Pratonton semua terjemahan yang ada bagi templat ini',
	'right-centralnotice_admin_rights' => 'Mengurus pemberitahuan pusat',
	'right-centralnotice_translate_rights' => 'Menterjemah pemberitahuan pusat',
	'action-centralnotice_admin_rights' => 'mengurus pemberitahuan pusat',
	'action-centralnotice_translate_rights' => 'menterjemah pemberitahuan pusat',
);

/** Low German (Plattdüütsch)
 * @author Slomox
 */
$messages['nds'] = array(
	'centralnotice-desc' => 'Föögt en zentrale Naricht för de Websteed to',
);

/** Dutch (Nederlands)
 * @author Siebrand
 */
$messages['nl'] = array(
	'centralnotice' => 'Beheer centrale sitenotice',
	'noticetemplate' => 'Sjablonen centrale sitenotice',
	'centralnotice-desc' => 'Voegt een centrale sitemededeling toe',
	'centralnotice-summary' => 'Met deze module kunnen centraal ingestelde sitenotices bewerkt worden.
De module kan ook gebruikt worden om sitenotices toe te voegen of oude te verwijderen.',
	'centralnotice-notice-name' => 'Sitenoticenaam',
	'centralnotice-end-date' => 'Einddatum',
	'centralnotice-enabled' => 'Actief',
	'centralnotice-modify' => 'Opslaan',
	'centralnotice-remove' => 'Verwijderen',
	'centralnotice-translate-heading' => 'Vertalen voor $1',
	'centralnotice-manage' => 'Centrale sitenotice beheren',
	'centralnotice-add' => 'Toevoegen',
	'centralnotice-add-notice' => 'Sitenotice toevoegen',
	'centralnotice-add-template' => 'Sjabloon toevoegen',
	'centralnotice-english' => 'Engels',
	'centralnotice-template-name' => 'Sjabloonnaam',
	'centralnotice-templates' => 'Sjablonen',
	'centralnotice-weight' => 'Gewicht',
	'centralnotice-locked' => 'Afgesloten',
	'centralnotice-notices' => 'Sitenotices',
	'centralnotice-notice-exists' => 'De sitenotice bestaat al.
Deze wordt niet toegevoegd.',
	'centralnotice-template-exists' => 'Het sjabloon bestaat al.
Dit wordt niet toegevoegd.',
	'centralnotice-notice-doesnt-exist' => 'De sitenotice bestaat niet.
Er is niets te verwijderen',
	'centralnotice-template-still-bound' => 'Het sjabloon is nog gekoppeld aan een sitenotice.
Het wordt niet verwijderd.',
	'centralnotice-day' => 'Dag',
	'centralnotice-year' => 'Jaar',
	'centralnotice-month' => 'Maand',
	'centralnotice-hours' => 'Uur',
	'centralnotice-min' => 'Minuut',
	'centralnotice-project-lang' => 'Projecttaal',
	'centralnotice-project-name' => 'Projectnaam',
	'centralnotice-start-date' => 'Startdatum',
	'centralnotice-start-time' => 'Starttijd (UTC)',
	'centralnotice-assigned-templates' => 'Toegewezen sjablonen',
	'centralnotice-no-templates' => 'Er zijn geen sjablonen beschikbaar in het systeem',
	'centralnotice-no-templates-assigned' => 'Er zijn geen sjablonen toegewezen aan de sitenotice.
Die moet u toevoegen.',
	'centralnotice-available-templates' => 'Beschikbare sjablonen',
	'centralnotice-template-already-exists' => 'Het sjabloon is al gekoppeld aan een campagne.
Het wordt niet toegevoegd',
	'centralnotice-preview-template' => 'Voorvertoning sjabloon',
	'centralnotice-start-hour' => 'Starttijd',
	'centralnotice-change-lang' => 'Te vertalen taal wijzigen',
	'centralnotice-weights' => 'Gewichten',
	'centralnotice-notice-is-locked' => 'De sitenotice is afgesloten.
Deze wordt niet verwijderd',
	'centralnotice-invalid-date-range' => 'Ongeldige datumreeks.
Er wordt niet bijgewerkt',
	'centralnotice-null-string' => 'U kunt geen leeg tekstveld toevoegen.
Er wordt niet toegevoegd.',
	'centralnotice-confirm-delete' => 'Weet u zeker dat u dit item wilt verwijderen?
Deze handeling is niet terug te draaien.',
	'centralnotice-no-notices-exist' => 'Er zijn geen sitenotices.
U kunt er hieronder een toevoegen',
	'centralnotice-number-uses' => 'Aantal keren gebruikt',
	'centralnotice-edit-template' => 'Sjabloon bewerken',
	'centralnotice-message' => 'Bericht',
	'centralnotice-message-not-set' => 'Het bericht is niet ingesteld',
	'centralnotice-clone' => 'Kopiëren',
	'centralnotice-clone-notice' => 'Een kopie van het sjabloon maken',
	'centralnotice-preview-all-template-translations' => 'Alle beschikbare vertalingen van het sjabloon bekijken',
	'right-centralnotice_admin_rights' => 'Centrale sitenotices beheren',
	'right-centralnotice_translate_rights' => 'Centrale sitenotices vertalen',
	'action-centralnotice_admin_rights' => 'centrale sitenotices beheren',
	'action-centralnotice_translate_rights' => 'centrale sitenotices vertalen',
);

/** Norwegian (bokmål)‬ (‪Norsk (bokmål)‬)
 * @author Jon Harald Søby
 * @author Laaknor
 */
$messages['no'] = array(
	'centralnotice' => 'Administrasjon av sentrale beskjeder',
	'noticetemplate' => 'Mal for sentrale beskjeder',
	'centralnotice-desc' => 'Legger til en sentral sidenotis',
	'centralnotice-summary' => 'Denne modulen lar deg redigere din nåværende sentralmeldinger.
Den kan også bli brukt for å legge til eller fjerne gamle meldinger.',
	'centralnotice-query' => 'Endre nåværende meldinger',
	'centralnotice-notice-name' => 'Meldingsnavn',
	'centralnotice-end-date' => 'Sluttdato',
	'centralnotice-enabled' => 'Aktivert',
	'centralnotice-modify' => 'Lagre',
	'centralnotice-preview' => 'Forhåndsvis',
	'centralnotice-add-new' => 'Legg til en ny sentralmelding',
	'centralnotice-remove' => 'Fjern',
	'centralnotice-translate-heading' => 'Oversettelse for $1',
	'centralnotice-manage' => 'Håndter sentralemeldinger',
	'centralnotice-add' => 'Legg til',
	'centralnotice-add-notice' => 'Legg til en melding',
	'centralnotice-add-template' => 'Legg til en mal',
	'centralnotice-show-notices' => 'Vis meldinger',
	'centralnotice-list-templates' => 'Vis maler',
	'centralnotice-translations' => 'Oversettelser',
	'centralnotice-translate-to' => 'Oversett til',
	'centralnotice-translate' => 'Oversett',
	'centralnotice-english' => 'Engelsk',
	'centralnotice-template-name' => 'Malnavn',
	'centralnotice-templates' => 'Maler',
	'centralnotice-weight' => 'Vekt',
	'centralnotice-locked' => 'Låst',
	'centralnotice-notices' => 'Meldinger',
	'centralnotice-notice-exists' => 'Melding eksisterer allerede.
Ikke lagt inn.',
	'centralnotice-template-exists' => 'Mal finnes allerede.
Ikke lagt inn',
	'centralnotice-notice-doesnt-exist' => 'Melding finnes ikke.
Ingenting å slette',
	'centralnotice-template-still-bound' => 'Mal er fortsatt koblet til en melding.
Ikke fjernet',
	'centralnotice-template-body' => 'Malinnhold:',
	'centralnotice-day' => 'Dag',
	'centralnotice-year' => 'År',
	'centralnotice-month' => 'Måned',
	'centralnotice-hours' => 'Timer',
	'centralnotice-min' => 'Minutt',
	'centralnotice-project-lang' => 'Prosjektspråk',
	'centralnotice-project-name' => 'Prosjektnavn',
	'centralnotice-start-date' => 'Startdato',
	'centralnotice-start-time' => 'Starttid (UTC)',
	'centralnotice-assigned-templates' => 'Tildelte maler',
	'centralnotice-no-templates' => 'Ingen maler funnet.
Legg til noen!',
	'centralnotice-no-templates-assigned' => 'Ingen maler tildelt melding.
Legg til noen!',
	'centralnotice-available-templates' => 'Tilgjengelige maler',
	'centralnotice-template-already-exists' => 'Mal er allerede knyttet til kampanje.
Ikke lagt inn',
	'centralnotice-preview-template' => 'Forhåndsvis mal',
	'centralnotice-start-hour' => 'Starttid',
	'centralnotice-change-lang' => 'Endre oversettelsesspråk',
	'centralnotice-weights' => 'Tyngder',
	'centralnotice-notice-is-locked' => 'Melding er låst.
Ikke fjernet',
	'centralnotice-overlap' => 'Melding overlapper tiden til en annen melding.
Ikke lagt inn',
	'centralnotice-invalid-date-range' => 'Ugyldig tidsrom.
Ikke oppdatert',
	'centralnotice-null-string' => 'Kan ikke legge til en nullstreng.
Ikke lagt til',
);

/** Occitan (Occitan)
 * @author Cedric31
 */
$messages['oc'] = array(
	'centralnotice' => 'Administracion de las notificacions centralas',
	'noticetemplate' => 'Modèls de las notificacions centralas',
	'centralnotice-desc' => 'Apond un sitenotice central',
	'centralnotice-summary' => 'Aqueste modul vos permet de modificar vòstres paramètres de las notificacions centralas.',
	'centralnotice-query' => 'Modificar las notificacions actualas',
	'centralnotice-notice-name' => 'Nom de la notificacion',
	'centralnotice-end-date' => 'Data de fin',
	'centralnotice-enabled' => 'Activat',
	'centralnotice-modify' => 'Sometre',
	'centralnotice-preview' => 'Previsualizacion',
	'centralnotice-add-new' => 'Apondre una notificacion centrala novèla',
	'centralnotice-remove' => 'Suprimir',
	'centralnotice-translate-heading' => 'Traduccion de $1',
	'centralnotice-manage' => 'Gerir las notificacions centralas',
	'centralnotice-add' => 'Apondre',
	'centralnotice-add-notice' => 'Apondre una notificacion',
	'centralnotice-add-template' => 'Apondre un modèl',
	'centralnotice-show-notices' => 'Afichar las notificacions',
	'centralnotice-list-templates' => 'Listar los modèls',
	'centralnotice-translations' => 'Traduccions',
	'centralnotice-translate-to' => 'Traduire en',
	'centralnotice-translate' => 'Traduire',
	'centralnotice-english' => 'Anglés',
	'centralnotice-template-name' => 'Nom del modèl',
	'centralnotice-templates' => 'Modèls',
	'centralnotice-weight' => 'Pes',
	'centralnotice-locked' => 'Varrolhat',
	'centralnotice-notices' => 'Notificacions',
	'centralnotice-notice-exists' => 'La notificacion existís ja.
Es pas estada aponduda.',
	'centralnotice-template-exists' => 'Lo modèl existís ja.
Es pas estat apondut.',
	'centralnotice-notice-doesnt-exist' => 'La notificacion existís pas.
I a pas res de suprimir.',
	'centralnotice-template-still-bound' => 'Lo modèl es encara religat a una notificacion.
Es pas estat suprimit.',
	'centralnotice-template-body' => 'Còs del modèl :',
	'centralnotice-day' => 'Jorn',
	'centralnotice-year' => 'Annada',
	'centralnotice-month' => 'Mes',
	'centralnotice-hours' => 'Ora',
	'centralnotice-min' => 'Minuta',
	'centralnotice-project-lang' => 'Lenga del projècte',
	'centralnotice-project-name' => 'Nom del projècte',
	'centralnotice-start-date' => 'Data de començament',
	'centralnotice-start-time' => 'Ora de començament (UTC)',
	'centralnotice-assigned-templates' => 'Modèls assignats',
	'centralnotice-no-templates' => 'I a pas de modèl dins lo sistèma.
Apondètz-ne un !',
	'centralnotice-no-templates-assigned' => 'Pas cap de modèl assignat a la notificacion.
Apondètz-ne un !',
	'centralnotice-available-templates' => 'Modèls disponibles',
	'centralnotice-template-already-exists' => "Lo modèl je es estacat a una campanha.
D'apondre pas",
	'centralnotice-preview-template' => 'Previsualizacion del modèl',
	'centralnotice-start-hour' => 'Ora de començament',
	'centralnotice-change-lang' => 'Modificar la lenga de traduccion',
	'centralnotice-weights' => 'Pes',
	'centralnotice-notice-is-locked' => 'La notificacion es varrolhada.
Es pas estada suprimida.',
	'centralnotice-overlap' => "Notificacion que s’imbrica dins lo temps d’una autra.
D'apondre pas.",
	'centralnotice-invalid-date-range' => 'Triada de data incorrècta.
De metre pas a jorn.',
	'centralnotice-null-string' => "Pòt pas apondre una cadena nulla.
D'apondre pas.",
	'centralnotice-confirm-delete' => 'Sètz segur(a) que volètz suprimir aqueste article ?
Aquesta accion poirà pas pus èsser recuperada.',
	'centralnotice-no-notices-exist' => 'Cap de notificacion existís pas.
Apondètz-ne una en dejós.',
	'centralnotice-no-templates-translate' => 'I a pas cap de modèl de traduire',
	'centralnotice-number-uses' => 'Utilizaires',
	'centralnotice-edit-template' => 'Modificar lo modèl',
	'centralnotice-message' => 'Messatge',
	'centralnotice-message-not-set' => 'Messatge pas entresenhat',
	'right-centralnotice_admin_rights' => 'Gerís las notificacions centralas',
	'right-centralnotice_translate_rights' => 'Traduire las notificacions centralas',
	'action-centralnotice_admin_rights' => 'gerir las notificacions centralas',
	'action-centralnotice_translate_rights' => 'traduire las notificacions centralas',
);

/** Ossetic (Иронау)
 * @author Amikeco
 */
$messages['os'] = array(
	'centralnotice-translations' => 'Тæлмацтæ',
	'centralnotice-year' => 'Аз',
	'centralnotice-project-lang' => 'Проекты æвзаг',
	'centralnotice-project-name' => 'Проекты ном',
);

/** Polish (Polski)
 * @author Derbeth
 * @author Leinad
 * @author Maikking
 * @author Qblik
 * @author Sp5uhe
 */
$messages['pl'] = array(
	'centralnotice' => 'Administrowanie wspólnymi komunikatami',
	'noticetemplate' => 'Szablony wspólnych komunikatów',
	'centralnotice-desc' => 'Dodaje wspólny komunikat dla serwisów',
	'centralnotice-summary' => 'Ten moduł pozwala zmieniać bieżące ustawienia wspólnych komunikatów.
Można także dodawać i usuwać komunikaty.',
	'centralnotice-notice-name' => 'Nazwa komunikatu',
	'centralnotice-end-date' => 'Data zakończenia',
	'centralnotice-enabled' => 'Włączony',
	'centralnotice-modify' => 'Zapisz',
	'centralnotice-remove' => 'Usuń',
	'centralnotice-translate-heading' => 'Tłumaczenie dla $1',
	'centralnotice-manage' => 'Zarządzaj wspólnymi komunikatami',
	'centralnotice-add' => 'Dodaj',
	'centralnotice-add-notice' => 'Dodaj komunikat',
	'centralnotice-add-template' => 'Dodaj szablon',
	'centralnotice-english' => 'Angielski',
	'centralnotice-template-name' => 'Nazwa szablonu',
	'centralnotice-templates' => 'Szablony',
	'centralnotice-weight' => 'Waga',
	'centralnotice-locked' => 'Zablokowany',
	'centralnotice-notices' => 'Komunikaty',
	'centralnotice-notice-exists' => 'Komunikat o podanej nazwie już istnieje. Nowy komunikat nie został dodany.',
	'centralnotice-template-exists' => 'Szablon o podanej nazwie już istnieje. Nowy szablon nie został dodany.',
	'centralnotice-notice-doesnt-exist' => 'Komunikat nie istnieje. Nie ma czego usunąć.',
	'centralnotice-template-still-bound' => 'Szablon nie może zostać usunięty. Jest ciągle używany przez komunikat.',
	'centralnotice-day' => 'Dzień',
	'centralnotice-year' => 'Rok',
	'centralnotice-month' => 'Miesiąc',
	'centralnotice-hours' => 'Godzina',
	'centralnotice-min' => 'Minuta',
	'centralnotice-project-lang' => 'Język projektu',
	'centralnotice-project-name' => 'Nazwa projektu',
	'centralnotice-start-date' => 'Data rozpoczęcia',
	'centralnotice-start-time' => 'Czas rozpoczęcia (UTC)',
	'centralnotice-assigned-templates' => 'Dołączone szablony',
	'centralnotice-no-templates' => 'Brak szablonów w bazie modułu',
	'centralnotice-no-templates-assigned' => 'Nie dołączono szablonów do komunikatu.
Dodaj jakiś szablon!',
	'centralnotice-available-templates' => 'Dostępne szablony',
	'centralnotice-template-already-exists' => 'Szablon nie został dodany.
Jest już wykorzystany w kampani.',
	'centralnotice-preview-template' => 'Podgląd szablonu',
	'centralnotice-start-hour' => 'Czas rozpoczęcia',
	'centralnotice-change-lang' => 'Zmień język tłumaczenia',
	'centralnotice-weights' => 'Wagi',
	'centralnotice-notice-is-locked' => 'Komunikat nie może zostać usunięty, ponieważ jest zablokowany.',
	'centralnotice-invalid-date-range' => 'Nieprawidłowy przedział pomiędzy datą rozpoczęcia a zakończenia.
Komunikat nie został zaktualizowany.',
	'centralnotice-null-string' => 'Nie można dodać pustej zawartości.',
	'centralnotice-confirm-delete' => 'Czy jesteś pewien, że chcesz usunąć ten element?
Działanie to będzie nieodwracalne.',
	'centralnotice-no-notices-exist' => 'Brak komunikatów.
Dodaj nowy poniżej.',
	'centralnotice-number-uses' => 'Zastosowania',
	'centralnotice-edit-template' => 'Edycja szablonu',
	'centralnotice-message' => 'Wiadomość',
	'centralnotice-message-not-set' => 'Wiadomość nie jest ustawiona',
	'centralnotice-clone' => 'Kopia',
	'centralnotice-clone-notice' => 'Utwórz kopię szablonu',
	'centralnotice-preview-all-template-translations' => 'Zobacz wszystkie dostępne tłumaczenia szablonu',
	'right-centralnotice_admin_rights' => 'Zarządzać wspólnymi komunikatami',
	'right-centralnotice_translate_rights' => 'Tłumaczyć wspólne komunikaty',
	'action-centralnotice_admin_rights' => 'zarządzaj centralnymi komunikatami',
	'action-centralnotice_translate_rights' => 'przetłumacz centralne komunikaty',
);

/** Pashto (پښتو)
 * @author Ahmed-Najib-Biabani-Ibrahimkhel
 */
$messages['ps'] = array(
	'centralnotice-desc' => 'يو مرکزي ويبځی-يادښت ورګډول',
);

/** Portuguese (Português)
 * @author Malafaya
 */
$messages['pt'] = array(
	'centralnotice-desc' => 'Adiciona um aviso do sítio centralizado',
	'centralnotice-end-date' => 'Data fim',
	'centralnotice-modify' => 'Submeter',
	'centralnotice-remove' => 'Remover',
	'centralnotice-translate-heading' => 'Tradução de $1',
	'centralnotice-add' => 'Adicionar',
	'centralnotice-translations' => 'Traduções',
	'centralnotice-english' => 'Inglês',
	'centralnotice-weight' => 'Peso',
	'centralnotice-day' => 'Dia',
	'centralnotice-year' => 'Ano',
	'centralnotice-month' => 'Mês',
	'centralnotice-hours' => 'Hora',
	'centralnotice-min' => 'Minuto',
	'centralnotice-project-lang' => 'Língua do projecto',
	'centralnotice-project-name' => 'Nome do projecto',
	'centralnotice-start-date' => 'Data início',
	'centralnotice-start-time' => 'Hora início (UTC)',
	'centralnotice-start-hour' => 'Hora início',
	'centralnotice-weights' => 'Pesos',
	'centralnotice-number-uses' => 'Utilizações',
	'centralnotice-message' => 'Mensagem',
	'centralnotice-message-not-set' => 'Mensagem não estabelecida',
);

/** Romanian (Română)
 * @author Mihai
 */
$messages['ro'] = array(
	'centralnotice-desc' => 'Adaugă un anunţ central sitului',
);

/** Russian (Русский)
 * @author Александр Сигачёв
 */
$messages['ru'] = array(
	'centralnotice' => 'Управление централизованными уведомлениями',
	'noticetemplate' => 'Шаблон централизованного уведомления',
	'centralnotice-desc' => 'Добавляет общее сообщение сайта',
	'centralnotice-summary' => 'Этот модуль позволяет вам изменять ваши текущие централизованные уведомления.
Он также может использоваться для добавления новых и удаления старых уведомлений.',
	'centralnotice-notice-name' => 'Название уведомления',
	'centralnotice-end-date' => 'Дата окончания',
	'centralnotice-enabled' => 'Включено',
	'centralnotice-modify' => 'Отправить',
	'centralnotice-remove' => 'Удалить',
	'centralnotice-translate-heading' => 'Перевод для $1',
	'centralnotice-manage' => 'Управление централизованными уведомлениями',
	'centralnotice-add' => 'Добавить',
	'centralnotice-add-notice' => 'Добавить уведомление',
	'centralnotice-add-template' => 'Добавить шаблон',
	'centralnotice-english' => 'английский',
	'centralnotice-template-name' => 'Название шаблона',
	'centralnotice-templates' => 'Шаблоны',
	'centralnotice-weight' => 'Ширина',
	'centralnotice-locked' => 'Заблокированный',
	'centralnotice-notices' => 'уведомления',
	'centralnotice-notice-exists' => 'Уведомление уже существует.
Не добавляется',
	'centralnotice-template-exists' => 'Шаблон уже существует.
Не добавляется',
	'centralnotice-notice-doesnt-exist' => 'Уведомления не существует.
Нечего удалять',
	'centralnotice-template-still-bound' => 'Шаблон по-прежнему связано с уведомлением.
Не удаляется',
	'centralnotice-day' => 'День',
	'centralnotice-year' => 'Год',
	'centralnotice-month' => 'Месяц',
	'centralnotice-hours' => 'Час',
	'centralnotice-min' => 'Минута',
	'centralnotice-project-lang' => 'Язык проекта',
	'centralnotice-project-name' => 'Название проекта',
	'centralnotice-start-date' => 'Дата начала',
	'centralnotice-start-time' => 'Время начала (UTC)',
	'centralnotice-assigned-templates' => 'Установленные шаблоны',
	'centralnotice-no-templates' => 'Не найдено шаблонов.
Добавьте что-нибудь!',
	'centralnotice-no-templates-assigned' => 'Нет связанных с уведомлением шаблонов.
Добавьте какой-нибудь',
	'centralnotice-available-templates' => 'Доступные шаблоны',
	'centralnotice-template-already-exists' => 'Шаблон уже привязан.
Не добавлен',
	'centralnotice-preview-template' => 'Предпросмотр шаблона',
	'centralnotice-start-hour' => 'Время начала',
	'centralnotice-change-lang' => 'Изменить язык перевода',
	'centralnotice-weights' => 'Веса',
	'centralnotice-notice-is-locked' => 'Уведомление заблокировано.
Не удаляется',
	'centralnotice-invalid-date-range' => 'Ошибочный диапазон дат.
Не обновляется',
	'centralnotice-null-string' => 'Невозможно добавить пустую строку.
Не добавляется',
	'centralnotice-confirm-delete' => 'Вы уверены в решении удалить этот элемент?
Это действие нельзя будет отменить.',
	'centralnotice-no-notices-exist' => 'Нет уведомлений.
Можно добавить',
	'centralnotice-number-uses' => 'Используются',
	'centralnotice-edit-template' => 'Править шаблон',
	'centralnotice-message' => 'Сообщение',
	'centralnotice-message-not-set' => 'Сообщение не установлено',
	'centralnotice-clone' => 'Клонирование',
	'centralnotice-clone-notice' => 'Создать копию шаблона',
	'centralnotice-preview-all-template-translations' => 'Просмотреть все доступные переводы шаблона',
	'right-centralnotice_admin_rights' => 'Управление централизованными уведомлениями',
	'right-centralnotice_translate_rights' => 'Перевод централизованных уведомлений',
	'action-centralnotice_admin_rights' => 'управление централизованными уведомлениями',
	'action-centralnotice_translate_rights' => 'перевод централизованных уведомлений',
);

/** Yakut (Саха тыла)
 * @author HalanTul
 */
$messages['sah'] = array(
	'centralnotice-desc' => 'Саайт биллэриитин эбэр',
);

/** Slovak (Slovenčina)
 * @author Helix84
 */
$messages['sk'] = array(
	'centralnotice' => 'Centrálny oznam',
	'noticetemplate' => 'Šablóna centrálneho oznamu',
	'centralnotice-desc' => 'Pridáva centrálnu Správu lokality',
	'centralnotice-summary' => 'Tento modul umožňuje upravovať vaše momentálne nastavené centrálne oznamy.
Tiež ho môžete použiť na pridanie alebo odstránenie starých oznamov.',
	'centralnotice-notice-name' => 'Názov oznamu',
	'centralnotice-end-date' => 'Dátum ukončenia',
	'centralnotice-enabled' => 'Zapnutá',
	'centralnotice-modify' => 'Odoslať',
	'centralnotice-remove' => 'Odstrániť',
	'centralnotice-translate-heading' => 'Preklad $1',
	'centralnotice-manage' => 'Správa centrálnych oznamov',
	'centralnotice-add' => 'Pridať',
	'centralnotice-add-notice' => 'Pridať oznam',
	'centralnotice-add-template' => 'Pridať šablónu',
	'centralnotice-english' => 'angličtina',
	'centralnotice-template-name' => 'Názov šablóny',
	'centralnotice-templates' => 'Šablóny',
	'centralnotice-weight' => 'Váha',
	'centralnotice-locked' => 'Zamknutý',
	'centralnotice-notices' => 'Oznamy',
	'centralnotice-notice-exists' => 'Oznam už existuje. Nebude pridaný.',
	'centralnotice-template-exists' => 'Šablóna už existuje. Nebude pridaná.',
	'centralnotice-notice-doesnt-exist' => 'Oznam neexistuje. Nebude odstránený.',
	'centralnotice-template-still-bound' => 'Šablóna je ešte stále naviazaná na oznam. Nebude odstránená.',
	'centralnotice-day' => 'Deň',
	'centralnotice-year' => 'Rok',
	'centralnotice-month' => 'Mesiac',
	'centralnotice-hours' => 'Hodina',
	'centralnotice-min' => 'Minúta',
	'centralnotice-project-lang' => 'Jazyk projektu',
	'centralnotice-project-name' => 'Názov projektu',
	'centralnotice-start-date' => 'Dátum začatia',
	'centralnotice-start-time' => 'Čas začatia (UTC)',
	'centralnotice-assigned-templates' => 'Priradené šablóny',
	'centralnotice-no-templates' => 'Neboli nájdené žiadne šablóny. Pridajte nejaké!',
	'centralnotice-no-templates-assigned' => 'Žiadne šablóny neboli priradené oznamom. Pridajte nejaké!',
	'centralnotice-available-templates' => 'Dostupné šablóny',
	'centralnotice-template-already-exists' => 'Šablóna sa už viaže na kampaň. Nebude pridaná.',
	'centralnotice-preview-template' => 'Náhľad šablóny',
	'centralnotice-start-hour' => 'Dátum začiatku',
	'centralnotice-change-lang' => 'Zmeniť jazyk prekladu',
	'centralnotice-weights' => 'Váhy',
	'centralnotice-notice-is-locked' => 'Oznam je zamknutý. Nebude odstránený.',
	'centralnotice-invalid-date-range' => 'Neplatný rozsah dátumov. Nebude aktualizovaný.',
	'centralnotice-null-string' => 'Nemožno pridať prázdny reťazec. Nebude pridaný.',
	'centralnotice-confirm-delete' => 'Ste si istý, že chcete zmazať túto položku?
Túto operáciu nebude možné vrátiť.',
	'centralnotice-no-notices-exist' => 'Neexistujú žiadne oznamy. Môžete ich pridať.',
	'centralnotice-number-uses' => 'Použitia',
	'centralnotice-edit-template' => 'Upraviť šablónu',
	'centralnotice-message' => 'Správa',
	'centralnotice-message-not-set' => 'Správa nebola nastavená',
	'centralnotice-clone' => 'Klonovať',
	'centralnotice-clone-notice' => 'Vytvoriť kópiu šablóny',
	'centralnotice-preview-all-template-translations' => 'Náhľad všetkých dostupných verzií šablóny',
	'right-centralnotice_admin_rights' => 'Spravovať centrálne oznamy',
	'right-centralnotice_translate_rights' => 'Prekladať centrálne oznamy',
	'action-centralnotice_admin_rights' => 'spravovať centrálne oznamy',
	'action-centralnotice_translate_rights' => 'prekladať centrálne oznamy',
);

/** Serbian Cyrillic ekavian (ћирилица)
 * @author Millosh
 * @author Јованвб
 */
$messages['sr-ec'] = array(
	'centralnotice-desc' => 'Додаје централну напомену на сајт.',
	'centralnotice-query' => 'Измени тренутна обавештења',
	'centralnotice-notice-name' => 'Име обавештења',
	'centralnotice-preview' => 'Прикажи',
	'centralnotice-add-new' => 'Додај нову централну напомену',
	'centralnotice-remove' => 'Уклони',
	'centralnotice-translate-heading' => 'Превод за $1',
	'centralnotice-manage' => 'Уреди централну напомену',
	'centralnotice-add' => 'Додај',
	'centralnotice-add-notice' => 'Додај обавештење',
	'centralnotice-add-template' => 'Додај шаблон',
	'centralnotice-show-notices' => 'Прикажи обавештења',
	'centralnotice-list-templates' => 'Списак шаблона',
	'centralnotice-translations' => 'Преводи',
	'centralnotice-translate-to' => 'Преведи на',
	'centralnotice-translate' => 'Преведи',
	'centralnotice-english' => 'Енглески',
	'centralnotice-template-name' => 'Име шаблона',
	'centralnotice-templates' => 'Шаблони',
	'centralnotice-notices' => 'Обавештења',
	'centralnotice-day' => 'Дан',
	'centralnotice-year' => 'Година',
	'centralnotice-month' => 'Месец',
	'centralnotice-hours' => 'Сат',
	'centralnotice-min' => 'Минут',
	'centralnotice-project-lang' => 'Име пројекта',
	'centralnotice-project-name' => 'Име пројекта',
	'centralnotice-no-templates' => 'Шаблони нису проађен.
Додај неки!',
	'centralnotice-preview-template' => 'Прикажи шаблон',
	'centralnotice-edit-template' => 'Измени шаблон',
	'centralnotice-message' => 'Порука',
);

/** Seeltersk (Seeltersk)
 * @author Pyt
 */
$messages['stq'] = array(
	'centralnotice-desc' => "Föiget ne zentroale ''sitenotice'' bietou",
);

/** Sundanese (Basa Sunda)
 * @author Kandar
 */
$messages['su'] = array(
	'centralnotice-desc' => 'Nambah émbaran puseur',
);

/** Swedish (Svenska)
 * @author Boivie
 * @author Lejonel
 * @author M.M.S.
 * @author Najami
 */
$messages['sv'] = array(
	'centralnotice' => 'Centralmeddelande-administration',
	'noticetemplate' => 'Centralmeddelande-mall',
	'centralnotice-desc' => 'Lägger till en central sitenotice',
	'centralnotice-summary' => 'Denna modul låter dig redigera din nuvarande uppsättning centralmeddelanden.
Den kan också användas för att lägga till eller ta bort gamla meddelanden.',
	'centralnotice-notice-name' => 'Meddelandenamn',
	'centralnotice-end-date' => 'Slutdatum',
	'centralnotice-enabled' => 'Aktiverad',
	'centralnotice-modify' => 'Verkställ',
	'centralnotice-remove' => 'Ta bort',
	'centralnotice-translate-heading' => 'Översättning för $1',
	'centralnotice-manage' => 'Hantera centralmeddelande',
	'centralnotice-add' => 'Lägg till',
	'centralnotice-add-notice' => 'Lägg till ett meddelande',
	'centralnotice-add-template' => 'Lägg till en mall',
	'centralnotice-english' => 'Engelska',
	'centralnotice-template-name' => 'Mallnamn',
	'centralnotice-templates' => 'Mallar',
	'centralnotice-weight' => 'Tyngd',
	'centralnotice-locked' => 'Låst',
	'centralnotice-notices' => 'Meddelanden',
	'centralnotice-notice-exists' => 'Meddelande existerar redan.
Lägger inte till',
	'centralnotice-template-exists' => 'Mall existerar redan.
Lägger inte till',
	'centralnotice-notice-doesnt-exist' => 'Meddelande existerar inte.
Inget att ta bort',
	'centralnotice-template-still-bound' => 'Mall är inte fortfarande kopplad till ett meddelande.
Tar inte bort.',
	'centralnotice-day' => 'Dag',
	'centralnotice-year' => 'År',
	'centralnotice-month' => 'Månad',
	'centralnotice-hours' => 'Timma',
	'centralnotice-min' => 'Minut',
	'centralnotice-project-lang' => 'Projektspråk',
	'centralnotice-project-name' => 'Projektnamn',
	'centralnotice-start-date' => 'Startdatum',
	'centralnotice-start-time' => 'Starttid (UTC)',
	'centralnotice-assigned-templates' => 'Använda mallar',
	'centralnotice-no-templates' => 'Inga mallar hittade.
Lägg till några!',
	'centralnotice-no-templates-assigned' => 'Inga mallar kopplade till meddelande.
Lägg till några!',
	'centralnotice-available-templates' => 'Tillgängliga mallar',
	'centralnotice-template-already-exists' => 'Mall är redan kopplad till kampanj.
Lägger inte till',
	'centralnotice-preview-template' => 'Förhandsgranska mall',
	'centralnotice-start-hour' => 'Starttid',
	'centralnotice-change-lang' => 'Ändra översättningsspråk',
	'centralnotice-weights' => 'Tyngder',
	'centralnotice-notice-is-locked' => 'Meddelande är låst.
Tar inte bort',
	'centralnotice-invalid-date-range' => 'Ogiltig tidsrymd.
Uppdaterar inte',
	'centralnotice-null-string' => 'Kan inte lägga till en nollsträng.
Lägger inte till',
	'centralnotice-confirm-delete' => 'Är du säker på att vill radera detta föremål?
Denna handling kan inte återställas.',
	'centralnotice-no-notices-exist' => 'Inga meddelanden existerar.
Lägg till ett nedan',
	'centralnotice-number-uses' => 'Användningar',
	'centralnotice-edit-template' => 'Redigera mall',
	'centralnotice-message' => 'Budskap',
	'centralnotice-message-not-set' => 'Budskap inte satt',
	'centralnotice-clone' => 'Klon',
	'centralnotice-clone-notice' => 'Skapa en kopia av mallen',
	'centralnotice-preview-all-template-translations' => 'Förhandsgranska alla tillgängliga översättningar av mallen',
	'right-centralnotice_admin_rights' => 'Hantera centralmeddelanden',
	'right-centralnotice_translate_rights' => 'Översätt centralmeddelanden',
	'action-centralnotice_admin_rights' => 'hantera centralmeddelanden',
	'action-centralnotice_translate_rights' => 'översätt centralmeddelanden',
);

/** Telugu (తెలుగు)
 * @author Chaduvari
 * @author Veeven
 */
$messages['te'] = array(
	'centralnotice-desc' => 'కేంద్రీయ సైటు గమనికని చేరుస్తుంది',
);

/** Tajik (Cyrillic) (Тоҷикӣ (Cyrillic))
 * @author Ibrahim
 */
$messages['tg-cyrl'] = array(
	'centralnotice-desc' => 'Як иттилооти маркази илова мекунад',
);

/** Ukrainian (Українська)
 * @author Ahonc
 */
$messages['uk'] = array(
	'centralnotice-desc' => 'Додає загальне повідомлення сайту',
);

/** Vèneto (Vèneto)
 * @author Candalua
 */
$messages['vec'] = array(
	'centralnotice' => 'Gestion notifiche sentralizade',
	'centralnotice-desc' => 'Zonta un aviso çentralizà in çima a la pagina (sitenotice)',
	'centralnotice-query' => 'Modìfega le notifiche corenti',
	'centralnotice-notice-name' => 'Nome de la notifica',
	'centralnotice-end-date' => 'Data de fine',
	'centralnotice-enabled' => 'Ativà',
	'centralnotice-modify' => 'Invia',
	'centralnotice-preview' => 'Anteprima',
	'centralnotice-add-new' => 'Zonta na notifica sentrale nova',
	'centralnotice-remove' => 'Cava',
	'centralnotice-translate-heading' => 'Tradussion par $1',
	'centralnotice-add' => 'Zonta',
	'centralnotice-add-notice' => 'Zonta na notifica',
	'centralnotice-add-template' => 'Zonta un modèl',
	'centralnotice-show-notices' => 'Mostra notifiche',
	'centralnotice-list-templates' => 'Elenca i modèi',
	'centralnotice-translations' => 'Tradussioni',
	'centralnotice-translate-to' => 'Tradusi con',
	'centralnotice-translate' => 'Tradusi',
	'centralnotice-english' => 'Inglese',
	'centralnotice-template-name' => 'Nome del modèl',
	'centralnotice-templates' => 'Modèi',
	'centralnotice-weight' => 'Peso',
	'centralnotice-notices' => 'Notifiche',
	'centralnotice-notice-exists' => 'Notifica zà esistente. 
Inserimento mia fato',
	'centralnotice-template-exists' => 'Modèl zà esistente. 
Inserimento mia fato',
	'centralnotice-notice-doesnt-exist' => 'Notifica mia esistente. 
Rimozion mia fata',
	'centralnotice-template-still-bound' => 'Modèl ancora ligà a na notifica. 
Rimozion mia fata.',
	'centralnotice-template-body' => 'Corpo del modèl:',
	'centralnotice-day' => 'Zorno',
	'centralnotice-year' => 'Ano',
	'centralnotice-month' => 'Mese',
	'centralnotice-hours' => 'Ora',
	'centralnotice-min' => 'Minuto',
	'centralnotice-project-lang' => 'Lengoa del projeto',
	'centralnotice-project-name' => 'Nome del projeto',
	'centralnotice-start-date' => 'Data de scominsio',
	'centralnotice-start-time' => 'Ora de scominsio (UTC)',
	'centralnotice-assigned-templates' => 'Modèi assegnà',
	'centralnotice-no-templates' => 'Nissun modèl catà.
Zónteghene qualchedun!',
	'centralnotice-no-templates-assigned' => 'Nissun modèl assegnà a la notifica
Zónteghene qualchedun!',
	'centralnotice-available-templates' => 'Modèi disponibili',
	'centralnotice-template-already-exists' => 'Sto modèl el xe zà ligà a na campagna. 
Inserimento mia fato',
	'centralnotice-preview-template' => 'Anteprima modèl',
	'centralnotice-start-hour' => 'Ora de scominsio',
	'centralnotice-change-lang' => 'Cànbia lengoa de tradussion',
	'centralnotice-weights' => 'Pesi',
	'centralnotice-notice-is-locked' => 'Notifica blocà.
Rimozion mia fata',
	'centralnotice-null-string' => 'No se pol zontar na stringa voda.
Inserimento mia fato',
	'centralnotice-no-notices-exist' => 'No esiste nissuna notifica.
Zónteghene una qua soto.',
	'centralnotice-edit-template' => 'Modìfega modèl',
	'centralnotice-message' => 'Messagio',
	'right-centralnotice_translate_rights' => 'Tradusi le notifiche sentrali',
);

/** Vietnamese (Tiếng Việt)
 * @author Minh Nguyen
 */
$messages['vi'] = array(
	'centralnotice' => 'Quản lý các thông báo chung',
	'noticetemplate' => 'Tiêu bản thông báo chung',
	'centralnotice-desc' => 'Thêm thông báo ở đầu các trang tại hơn một wiki',
	'centralnotice-summary' => 'Dùng phần này, bạn có thể sửa đổi các thông báo chung đã được thiết lập, cũng như thêm thông báo mới hoặc dời thông báo cũ.',
	'centralnotice-query' => 'Sửa đổi các thông báo hiện hành',
	'centralnotice-notice-name' => 'Tên thông báo',
	'centralnotice-end-date' => 'Ngày kết thúc',
	'centralnotice-enabled' => 'Đang hiện',
	'centralnotice-modify' => 'Lưu các thông báo',
	'centralnotice-preview' => 'Xem trước',
	'centralnotice-add-new' => 'Thêm thông báo chung mới',
	'centralnotice-remove' => 'Dời',
	'centralnotice-translate-heading' => 'Dịch $1',
	'centralnotice-manage' => 'Quản lý thông báo chung',
	'centralnotice-add' => 'Thêm',
	'centralnotice-add-notice' => 'Thêm thông báo',
	'centralnotice-add-template' => 'Thêm tiêu bản',
	'centralnotice-show-notices' => 'Xem các thông báo',
	'centralnotice-list-templates' => 'Liệt kê các tiêu bản',
	'centralnotice-translations' => 'Bản dịch',
	'centralnotice-translate-to' => 'Dịch ra',
	'centralnotice-translate' => 'Dịch',
	'centralnotice-english' => 'tiếng Anh',
	'centralnotice-template-name' => 'Tên tiêu bản',
	'centralnotice-templates' => 'Tiêu bản',
	'centralnotice-weight' => 'Mức ưu tiên',
	'centralnotice-locked' => 'Bị khóa',
	'centralnotice-notices' => 'Thông báo',
	'centralnotice-notice-exists' => 'Không thêm được: thông báo đã tồn tại.',
	'centralnotice-template-exists' => 'Không thêm được: tiêu bản đã tồn tại.',
	'centralnotice-notice-doesnt-exist' => 'Không dời được: thông báo không tồn tại.',
	'centralnotice-template-still-bound' => 'Không dời được: có thông báo dựa theo tiêu bản.',
	'centralnotice-template-body' => 'Nội dung tiêu bản:',
	'centralnotice-day' => 'Ngày',
	'centralnotice-year' => 'Năm',
	'centralnotice-month' => 'Tháng',
	'centralnotice-hours' => 'Giờ',
	'centralnotice-min' => 'Phút',
	'centralnotice-project-lang' => 'Ngôn ngữ của dự án',
	'centralnotice-project-name' => 'Tên dự án',
	'centralnotice-start-date' => 'Ngày bắt đầu',
	'centralnotice-start-time' => 'Lúc bắt đầu (UTC)',
	'centralnotice-assigned-templates' => 'Tiêu bản được sử dụng',
	'centralnotice-no-templates' => 'Hệ thống không chứa tiêu bản',
	'centralnotice-no-templates-assigned' => 'Thông báo không dùng tiêu bản nào. Hãy chỉ định tiêu bản!',
	'centralnotice-available-templates' => 'Tiêu bản có sẵn',
	'centralnotice-template-already-exists' => 'Không chỉ định được: thông báo đã sử dụng tiêu bản.',
	'centralnotice-preview-template' => 'Xem trước tiêu bản',
	'centralnotice-start-hour' => 'Lúc bắt đầu',
	'centralnotice-change-lang' => 'Thay đổi ngôn ngữ của bản dịch',
	'centralnotice-weights' => 'Mức ưu tiên',
	'centralnotice-notice-is-locked' => 'Không dời được: thông báo bị khóa.',
	'centralnotice-overlap' => 'Không thêm được: thông báo sẽ hiện cùng lúc với thông báo khác.',
	'centralnotice-invalid-date-range' => 'Không cập nhật được: thời gian không hợp lệ.',
	'centralnotice-null-string' => 'Không thêm được: chuỗi rỗng.',
	'centralnotice-confirm-delete' => 'Bạn có chắc muốn xóa thông báo hoặc tiêu bản này không? Không thể phục hồi nó.',
	'centralnotice-no-notices-exist' => 'Chưa có thông báo. Hãy thêm thông báo ở dưới.',
	'centralnotice-no-templates-translate' => 'Không có tiêu bản để dịch',
	'centralnotice-number-uses' => 'Số thông báo dùng',
	'centralnotice-edit-template' => 'Sửa đổi tiêu bản',
	'centralnotice-message' => 'Thông báo',
	'centralnotice-message-not-set' => 'Thông báo chưa được thiết lập',
	'right-centralnotice_admin_rights' => 'Quản lý thông báo chung',
	'right-centralnotice_translate_rights' => 'Dịch thông báo chung',
	'action-centralnotice_admin_rights' => 'quản lý thông báo chung',
	'action-centralnotice_translate_rights' => 'dịch thông báo chung',
);

/** Volapük (Volapük)
 * @author Malafaya
 * @author Smeira
 */
$messages['vo'] = array(
	'centralnotice-desc' => 'Läükön sitanulod zänodik',
	'centralnotice-translations' => 'Tradutods',
	'centralnotice-english' => 'Linglänapük',
	'centralnotice-day' => 'Del',
);

/** Yiddish (ייִדיש)
 * @author פוילישער
 */
$messages['yi'] = array(
	'centralnotice-translate-heading' => 'פֿאַרטייטשונג פֿאַר ִ$1',
	'centralnotice-translations' => 'פֿאַרטייטשונגען',
	'centralnotice-translate-to' => 'פֿאַרטייטשן אויף',
	'centralnotice-translate' => 'פֿאַרטייטשן',
	'centralnotice-english' => 'ענגליש',
	'centralnotice-template-name' => 'מוסטער נאמען',
	'centralnotice-templates' => 'מוסטערן',
	'centralnotice-day' => 'טאג',
	'centralnotice-year' => 'יאר',
	'centralnotice-month' => 'מאנאט',
	'centralnotice-hours' => 'שעה',
	'centralnotice-min' => 'מינוט',
);

/** Yue (粵語)
 * @author Shinjiman
 */
$messages['yue'] = array(
	'centralnotice-desc' => '加入一個中央公告欄',
);

/** Simplified Chinese (‪中文(简体)‬)
 * @author Alex S.H. Lin
 */
$messages['zh-hans'] = array(
	'centralnotice-desc' => '在页面的顶部增加統一的公告栏位',
);

/** Traditional Chinese (‪中文(繁體)‬)
 * @author Alex S.H. Lin
 */
$messages['zh-hant'] = array(
	'centralnotice-desc' => '在頁面頂端增加統一的公告欄位',
);

