<?php
/**
 * Aliases for special pages of CentralNotice extension.
 */

$specialPageAliases = array();

/** English (English) */
$specialPageAliases['en'] = array(
	'CentralNotice' => array( 'CentralNotice' ),
	'NoticeTemplate' => array( 'NoticeTemplate' ),
	'BannerAllocation' => array( 'BannerAllocation' ),
	'BannerController' => array( 'BannerController' ),
	'BannerListLoader' => array( 'BannerListLoader' ),
	'BannerLoader' => array( 'BannerLoader' ),
	'HideBanners' => array( 'HideBanners' ),
);

/** Arabic (العربية) */
$specialPageAliases['ar'] = array(
	'CentralNotice' => array( 'ملاحظة_مركزية' ),
	'NoticeTemplate' => array( 'قالب_الملاحظة' ),
);

/** Egyptian Spoken Arabic (مصرى) */
$specialPageAliases['arz'] = array(
	'CentralNotice' => array( 'ملاحظة_مركزية' ),
	'NoticeTemplate' => array( 'قالب_الملاحظة' ),
);

/** Persian (فارسی) */
$specialPageAliases['fa'] = array(
	'CentralNotice' => array( 'اعلامیه_مرکزی' ),
	'NoticeTemplate' => array( 'الگوی_اعلامیه' ),
);

/** Interlingua (Interlingua) */
$specialPageAliases['ia'] = array(
	'CentralNotice' => array( 'Aviso_central' ),
	'NoticeTemplate' => array( 'Patrono_de_aviso' ),
	'BannerAllocation' => array( 'Alloca_bandieras' ),
	'BannerController' => array( 'Controla_bandieras' ),
	'BannerListLoader' => array( 'Carga_lista_de_bandieras' ),
	'BannerLoader' => array( 'Carga_bandieras' ),
);

/** Japanese (日本語) */
$specialPageAliases['ja'] = array(
	'CentralNotice' => array( '中央管理通知' ),
	'NoticeTemplate' => array( '通知テンプレート' ),
	'BannerAllocation' => array( 'テンプレート割り当て' ),
	'BannerController' => array( 'テンプレート制御' ),
	'BannerListLoader' => array( 'テンプレート一覧読み込み' ),
	'BannerLoader' => array( 'テンプレート読み込み' ),
);

/** Ladino (Ladino) */
$specialPageAliases['lad'] = array(
	'CentralNotice' => array( 'AvisoCentral' ),
	'NoticeTemplate' => array( 'Xabblón_de_aviso' ),
);

/** Malayalam (മലയാളം) */
$specialPageAliases['ml'] = array(
	'CentralNotice' => array( 'കേന്ദ്രീകൃതഅറിയിപ്പ്' ),
	'NoticeTemplate' => array( 'അറിയിപ്പ്ഫലകം' ),
);

/** Dutch (Nederlands) */
$specialPageAliases['nl'] = array(
	'CentralNotice' => array( 'CentraleMededeling' ),
	'NoticeTemplate' => array( 'Mededelingsjabloon' ),
	'BannerAllocation' => array( 'Bannertoewijzing' ),
	'BannerController' => array( 'Bannerbeheerder' ),
	'BannerListLoader' => array( 'Bannerlijstlader' ),
	'BannerLoader' => array( 'Bannerlader' ),
);

/** Norwegian Nynorsk (‪Norsk (nynorsk)‬) */
$specialPageAliases['nn'] = array(
	'CentralNotice' => array( 'Sentralmerknad' ),
	'NoticeTemplate' => array( 'Merknadsmal' ),
);

/** Norwegian (bokmål)‬ (‪Norsk (bokmål)‬) */
$specialPageAliases['no'] = array(
	'CentralNotice' => array( 'Sentralnotis' ),
	'NoticeTemplate' => array( 'Notismal' ),
);

/** Polish (Polski) */
$specialPageAliases['pl'] = array(
	'CentralNotice' => array( 'Globalny_komunikat' ),
	'NoticeTemplate' => array( 'Szablon_komunikatu' ),
);

/** Traditional Chinese (‪中文(繁體)‬) */
$specialPageAliases['zh-hant'] = array(
	'CentralNotice' => array( '中央通告' ),
	'NoticeTemplate' => array( '通告模板' ),
);

/**
 * For backwards compatibility with MediaWiki 1.15 and earlier.
 */
$aliases =& $specialPageAliases;