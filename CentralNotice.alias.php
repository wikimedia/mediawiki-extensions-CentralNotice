<?php
/**
 * Aliases for special pages of CentralNotice extension.
 */

$specialPageAliases = array();

/** English (English) */
$specialPageAliases['en'] = array(
	'CentralNotice' => array( 'CentralNotice' ),
	'NoticeText' => array( 'NoticeText' ),
	'NoticeTemplate' => array( 'NoticeTemplate' ),
	'NoticeLocal' => array( 'NoticeLocal' ),
	'BannerAllocation' => array( 'BannerAllocation' ),
	'BannerController' => array( 'BannerController' ),
	'BannerListLoader' => array( 'BannerListLoader' ),
	'BannerLoader' => array( 'BannerLoader' ),
);

/** Arabic (العربية) */
$specialPageAliases['ar'] = array(
	'CentralNotice' => array( 'ملاحظة_مركزية' ),
	'NoticeText' => array( 'نص_الملاحظة' ),
	'NoticeTemplate' => array( 'قالب_الملاحظة' ),
);

/** Egyptian Spoken Arabic (مصرى) */
$specialPageAliases['arz'] = array(
	'CentralNotice' => array( 'ملاحظة_مركزية' ),
	'NoticeText' => array( 'نص_الملاحظة' ),
	'NoticeTemplate' => array( 'قالب_الملاحظة' ),
);

/** Persian (فارسی) */
$specialPageAliases['fa'] = array(
	'CentralNotice' => array( 'اعلامیه_مرکزی' ),
	'NoticeText' => array( 'متن_اعلامیه' ),
	'NoticeTemplate' => array( 'الگوی_اعلامیه' ),
	'NoticeLocal' => array( 'اعلامیه_محلی' ),
);

/** Interlingua (Interlingua) */
$specialPageAliases['ia'] = array(
	'CentralNotice' => array( 'Aviso_central' ),
	'NoticeText' => array( 'Texto_de_aviso' ),
	'NoticeTemplate' => array( 'Patrono_de_aviso' ),
	'NoticeLocal' => array( 'Aviso_local' ),
	'BannerAllocation' => array( 'Alloca_bandieras' ),
	'BannerController' => array( 'Controla_bandieras' ),
	'BannerListLoader' => array( 'Carga_lista_de_bandieras' ),
	'BannerLoader' => array( 'Carga_bandieras' ),
);

/** Japanese (日本語) */
$specialPageAliases['ja'] = array(
	'CentralNotice' => array( '中央管理通知' ),
	'NoticeText' => array( '通知文' ),
	'NoticeTemplate' => array( '通知テンプレート' ),
	'NoticeLocal' => array( 'ローカル通知' ),
	'BannerAllocation' => array( 'テンプレート割り当て' ),
	'BannerController' => array( 'テンプレート制御' ),
	'BannerListLoader' => array( 'テンプレート一覧読み込み' ),
	'BannerLoader' => array( 'テンプレート読み込み' ),
);

/** Ladino (Ladino) */
$specialPageAliases['lad'] = array(
	'CentralNotice' => array( 'AvisoCentral' ),
	'NoticeText' => array( 'Teksto_de_aviso' ),
	'NoticeTemplate' => array( 'Xabblón_de_aviso' ),
	'NoticeLocal' => array( 'AvisoLocal' ),
);

/** Malayalam (മലയാളം) */
$specialPageAliases['ml'] = array(
	'CentralNotice' => array( 'കേന്ദ്രീകൃതഅറിയിപ്പ്' ),
	'NoticeText' => array( 'അറിയിപ്പ്‌‌എഴുത്ത്' ),
	'NoticeTemplate' => array( 'അറിയിപ്പ്ഫലകം' ),
	'NoticeLocal' => array( 'പ്രാദേശിക‌‌അറിയിപ്പ്' ),
);

/** Dutch (Nederlands) */
$specialPageAliases['nl'] = array(
	'CentralNotice' => array( 'CentraleMededeling' ),
	'NoticeText' => array( 'Mededeling' ),
	'NoticeTemplate' => array( 'Mededelingsjabloon' ),
	'NoticeLocal' => array( 'LokaleMededeling' ),
	'BannerAllocation' => array( 'Bannertoewijzing' ),
	'BannerController' => array( 'Bannerbeheerder' ),
	'BannerListLoader' => array( 'Bannerlijstlader' ),
	'BannerLoader' => array( 'Bannerlader' ),
);

/** Norwegian Nynorsk (‪Norsk (nynorsk)‬) */
$specialPageAliases['nn'] = array(
	'CentralNotice' => array( 'Sentralmerknad' ),
	'NoticeText' => array( 'Merknadstekst' ),
	'NoticeTemplate' => array( 'Merknadsmal' ),
);

/** Norwegian (bokmål)‬ (‪Norsk (bokmål)‬) */
$specialPageAliases['no'] = array(
	'CentralNotice' => array( 'Sentralnotis' ),
	'NoticeText' => array( 'Notistekst' ),
	'NoticeTemplate' => array( 'Notismal' ),
);

/** Polish (Polski) */
$specialPageAliases['pl'] = array(
	'CentralNotice' => array( 'Globalny_komunikat' ),
	'NoticeText' => array( 'Treść_komunikatu' ),
	'NoticeTemplate' => array( 'Szablon_komunikatu' ),
);

/** Traditional Chinese (‪中文(繁體)‬) */
$specialPageAliases['zh-hant'] = array(
	'CentralNotice' => array( '中央通告' ),
	'NoticeText' => array( '通告內文' ),
	'NoticeTemplate' => array( '通告模板' ),
	'NoticeLocal' => array( '本地化通告' ),
);

/**
 * For backwards compatibility with MediaWiki 1.15 and earlier.
 */
$aliases =& $specialPageAliases;