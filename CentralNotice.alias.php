<?php
/**
 * Aliases for special pages of CentralNotice extension.
 */

$specialPageAliases = array();

/** English (English) */
$specialPageAliases['en'] = array(
	'CentralNotice' => array( 'CentralNotice' ),
	'CentralNoticeLogs' => array( 'CentralNoticeLogs' ),
	'NoticeTemplate' => array( 'NoticeTemplate' ),
	'GlobalAllocation' => array( 'GlobalAllocation' ),
	'BannerAllocation' => array( 'BannerAllocation' ),
	'BannerController' => array( 'BannerController' ),
	'BannerLoader' => array( 'BannerLoader' ),
	'BannerRandom' => array( 'BannerRandom' ),
	'RecordImpression' => array( 'RecordImpression' ),
	'HideBanners' => array( 'HideBanners' ),
);

/** Arabic (العربية) */
$specialPageAliases['ar'] = array(
	'CentralNotice' => array( 'ملاحظة_مركزية' ),
	'CentralNoticeLogs' => array( 'سجلات_الملاحظة_المركزية' ),
	'NoticeTemplate' => array( 'قالب_الملاحظة' ),
	'BannerAllocation' => array( 'وضع_الإعلان' ),
	'BannerController' => array( 'متحكم_الإعلان' ),
	'BannerLoader' => array( 'محمل_الإعلان' ),
	'HideBanners' => array( 'إخفاء_الإعلان' ),
);

/** Egyptian Spoken Arabic (مصرى) */
$specialPageAliases['arz'] = array(
	'CentralNotice' => array( 'ملاحظة_مركزية' ),
	'NoticeTemplate' => array( 'قالب_الملاحظة' ),
);

/** Assamese (অসমীয়া) */
$specialPageAliases['as'] = array(
	'CentralNotice' => array( 'কেন্দ্ৰীয়_জাননী' ),
	'CentralNoticeLogs' => array( 'কেন্দ্ৰীয়_জাননী_অভিলেখসমূহ' ),
	'NoticeTemplate' => array( 'জাননী_সাঁচ' ),
);

/** Breton (brezhoneg) */
$specialPageAliases['br'] = array(
	'HideBanners' => array( 'KuzhatBanniel' ),
);

/** German (Deutsch) */
$specialPageAliases['de'] = array(
	'CentralNotice' => array( 'Zentrale_Mitteilung' ),
	'CentralNoticeLogs' => array( 'Logbücher_zur_zentralen_Mitteilung' ),
	'NoticeTemplate' => array( 'Mitteilungsvorlage' ),
	'GlobalAllocation' => array( 'Globale_Anordnung' ),
	'BannerAllocation' => array( 'Vorlagenanordnung' ),
	'BannerController' => array( 'Vorlagensteuerung' ),
	'BannerLoader' => array( 'Vorlage_laden' ),
	'BannerRandom' => array( 'Zufällige_Vorlage' ),
	'RecordImpression' => array( 'Zugriffe_zählen' ),
	'HideBanners' => array( 'Vorlagen_ausblenden' ),
);

/** Zazaki (Zazaki) */
$specialPageAliases['diq'] = array(
	'CentralNotice' => array( 'MerkeziXeberdaren' ),
	'CentralNoticeLogs' => array( 'MerkeziİlanêRocekan' ),
	'NoticeTemplate' => array( 'ŞablonêXeberdaren' ),
	'BannerAllocation' => array( 'TahsisêAfişan' ),
	'BannerController' => array( 'KontrolkarêAfişan' ),
	'BannerLoader' => array( 'BarkerdenêAfişan' ),
	'HideBanners' => array( 'AfişanBınımnê' ),
);

/** Esperanto (Esperanto) */
$specialPageAliases['eo'] = array(
	'CentralNotice' => array( 'Centra_informilo' ),
	'NoticeTemplate' => array( 'Informŝablono' ),
	'BannerAllocation' => array( 'Strirezervado' ),
	'BannerController' => array( 'Informstrikontrolisto' ),
	'BannerLoader' => array( 'Informstriŝargilo' ),
	'HideBanners' => array( 'Kaŝi_striojn', 'Kaŝu_striojn' ),
);

/** Persian (فارسی) */
$specialPageAliases['fa'] = array(
	'CentralNotice' => array( 'اعلان_مرکزی' ),
	'CentralNoticeLogs' => array( 'سیاهه‌های_اعلان_مرکزی' ),
	'NoticeTemplate' => array( 'الگوی_اعلامیه' ),
	'BannerAllocation' => array( 'موقعیت_نشان' ),
	'BannerController' => array( 'کنترل_نشان' ),
	'BannerLoader' => array( 'بارگیری_نشان' ),
	'HideBanners' => array( 'پنهان_کردن_نشان‌ها' ),
);

/** Galician (galego) */
$specialPageAliases['gl'] = array(
	'CentralNotice' => array( 'Aviso_central' ),
	'CentralNoticeLogs' => array( 'Rexistro_do_aviso_central' ),
	'NoticeTemplate' => array( 'Modelo_de_aviso' ),
);

/** 湘语 (湘语) */
$specialPageAliases['hsn'] = array(
	'CentralNotice' => array( '中心公告' ),
	'NoticeTemplate' => array( '公告样范' ),
	'BannerAllocation' => array( '横幅配置' ),
	'BannerController' => array( '横幅控制器' ),
	'BannerLoader' => array( '横幅载入器' ),
	'HideBanners' => array( '隐藏横幅' ),
);

/** Haitian (Kreyòl ayisyen) */
$specialPageAliases['ht'] = array(
	'CentralNotice' => array( 'NòtSantral' ),
	'NoticeTemplate' => array( 'ModèlNòt' ),
	'BannerAllocation' => array( 'BayAnsey' ),
	'BannerController' => array( 'KontroleAnsey' ),
	'BannerLoader' => array( 'ChajeAnsey' ),
);

/** Interlingua (interlingua) */
$specialPageAliases['ia'] = array(
	'CentralNotice' => array( 'Aviso_central' ),
	'CentralNoticeLogs' => array( 'Registro_de_aviso_central' ),
	'NoticeTemplate' => array( 'Patrono_de_aviso' ),
	'BannerAllocation' => array( 'Alloca_bandieras' ),
	'BannerController' => array( 'Controla_bandieras' ),
	'BannerLoader' => array( 'Carga_bandieras' ),
	'HideBanners' => array( 'Celar_bandieras' ),
);

/** Italian (italiano) */
$specialPageAliases['it'] = array(
	'CentralNotice' => array( 'AvvisoCentralizzato' ),
	'CentralNoticeLogs' => array( 'RegistriAvvisoCentralizzato' ),
	'NoticeTemplate' => array( 'TemplateAvviso' ),
	'BannerAllocation' => array( 'DestinazioneBanner' ),
	'BannerController' => array( 'ControllerBanner' ),
	'BannerLoader' => array( 'CaricatoreBanner' ),
	'HideBanners' => array( 'NascondiBanner' ),
);

/** Japanese (日本語) */
$specialPageAliases['ja'] = array(
	'CentralNotice' => array( '中央管理通知' ),
	'CentralNoticeLogs' => array( '中央管理通知記録' ),
	'NoticeTemplate' => array( '通知テンプレート' ),
	'GlobalAllocation' => array( 'グローバル割り当て' ),
	'BannerAllocation' => array( 'テンプレート割り当て' ),
	'BannerController' => array( 'テンプレート制御' ),
	'BannerLoader' => array( 'テンプレート読み込み' ),
	'HideBanners' => array( 'バナーを隠す' ),
);

/** Georgian (ქართული) */
$specialPageAliases['ka'] = array(
	'HideBanners' => array( 'ბანერების_დამალვა' ),
);

/** Korean (한국어) */
$specialPageAliases['ko'] = array(
	'CentralNotice' => array( '중앙공지' ),
	'CentralNoticeLogs' => array( '중앙공지기록' ),
	'NoticeTemplate' => array( '알림틀', '공지틀' ),
	'GlobalAllocation' => array( '전역할당' ),
	'BannerAllocation' => array( '배너배정' ),
	'BannerController' => array( '배너컨트롤러' ),
	'BannerLoader' => array( '배너열기', '배너로더' ),
	'HideBanners' => array( '배너숨기기' ),
);

/** Cornish (kernowek) */
$specialPageAliases['kw'] = array(
	'CentralNotice' => array( 'ArgemmynCres' ),
	'CentralNoticeLogs' => array( 'CovnotennowArgemynnowCres' ),
	'NoticeTemplate' => array( 'ScantlynArgemynnow' ),
	'HideBanners' => array( 'CudhaBanerow' ),
);

/** Ladino (Ladino) */
$specialPageAliases['lad'] = array(
	'CentralNotice' => array( 'AvisoCentral' ),
	'NoticeTemplate' => array( 'Xabblón_de_aviso' ),
);

/** Luxembourgish (Lëtzebuergesch) */
$specialPageAliases['lb'] = array(
	'BannerController' => array( 'Bannersteierung' ),
	'HideBanners' => array( 'Banner_verstoppen' ),
);

/** Macedonian (македонски) */
$specialPageAliases['mk'] = array(
	'CentralNotice' => array( 'ЦентралноИзвестување' ),
	'CentralNoticeLogs' => array( 'ДневнициНаЦентралноИзвестување' ),
	'NoticeTemplate' => array( 'ШаблонЗаИзвестување' ),
	'GlobalAllocation' => array( 'ГлобалнаРаспределба' ),
	'BannerAllocation' => array( 'РаспределбаНаПлакати' ),
	'BannerController' => array( 'КонтролорНаПлакати' ),
	'BannerLoader' => array( 'ВчитувачНаПлакати' ),
	'BannerRandom' => array( 'ПлакатСлучаен' ),
	'RecordImpression' => array( 'ЗаведиВпечаток' ),
	'HideBanners' => array( 'СкријПлакати' ),
);

/** Malayalam (മലയാളം) */
$specialPageAliases['ml'] = array(
	'CentralNotice' => array( 'കേന്ദ്രീകൃതഅറിയിപ്പ്' ),
	'CentralNoticeLogs' => array( 'കേന്ദ്രീകൃതഅറിയിപ്പ്‌രേഖകൾ' ),
	'NoticeTemplate' => array( 'അറിയിപ്പ്ഫലകം' ),
);

/** Norwegian Bokmål (norsk bokmål) */
$specialPageAliases['nb'] = array(
	'CentralNotice' => array( 'Sentralnotis' ),
	'CentralNoticeLogs' => array( 'Sentralnotislogger' ),
	'NoticeTemplate' => array( 'Notismal' ),
	'BannerAllocation' => array( 'Bannerplassering' ),
	'BannerController' => array( 'Bannerkontroll' ),
	'BannerLoader' => array( 'Bannerlaster' ),
	'HideBanners' => array( 'Skjul_bannere' ),
);

/** Low Saxon (Netherlands) (Nedersaksies) */
$specialPageAliases['nds-nl'] = array(
	'CentralNotice' => array( 'Sentrale_mededeling' ),
	'NoticeTemplate' => array( 'Mededelingsmal' ),
	'BannerAllocation' => array( 'Baniertoewiezing' ),
	'BannerController' => array( 'Banierbeheerder' ),
	'BannerLoader' => array( 'Banierlaojer' ),
	'HideBanners' => array( 'Banierverbargen' ),
);

/** Dutch (Nederlands) */
$specialPageAliases['nl'] = array(
	'CentralNotice' => array( 'CentraleMededeling' ),
	'CentralNoticeLogs' => array( 'CentraleMededelingenlogboek' ),
	'NoticeTemplate' => array( 'Mededelingsjabloon' ),
	'BannerAllocation' => array( 'Bannertoewijzing' ),
	'BannerController' => array( 'Bannerbeheerder' ),
	'BannerLoader' => array( 'Bannerlader' ),
	'HideBanners' => array( 'BannersVerbergen' ),
);

/** Norwegian Nynorsk (norsk nynorsk) */
$specialPageAliases['nn'] = array(
	'CentralNotice' => array( 'Sentralmerknad' ),
	'NoticeTemplate' => array( 'Merknadsmal' ),
);

/** Oriya (ଓଡ଼ିଆ) */
$specialPageAliases['or'] = array(
	'CentralNotice' => array( 'ସୂଚନାଫଳକ' ),
	'CentralNoticeLogs' => array( 'ସୂଚନାଫଳକଲଗ' ),
	'NoticeTemplate' => array( 'ସୂଚନାଛାଞ୍ଚ' ),
);

/** Punjabi (ਪੰਜਾਬੀ) */
$specialPageAliases['pa'] = array(
	'CentralNotice' => array( 'ਕੇਂਦਰੀ_ਨੋਟਿਸ' ),
	'CentralNoticeLogs' => array( 'ਕੇਂਦਰੀ_ਨੋਟਿਸ_ਚਿੱਠੇ' ),
	'HideBanners' => array( 'ਬੈਨਰ_ਲੁਕਾਓ' ),
);

/** Polish (polski) */
$specialPageAliases['pl'] = array(
	'CentralNotice' => array( 'Globalny_komunikat' ),
	'NoticeTemplate' => array( 'Szablon_komunikatu' ),
);

/** Sicilian (sicilianu) */
$specialPageAliases['scn'] = array(
	'CentralNotice' => array( 'AvvisoCentralizzato' ),
	'CentralNoticeLogs' => array( 'RegistriAvvisoCentralizzato' ),
	'NoticeTemplate' => array( 'TemplateAvviso' ),
	'BannerAllocation' => array( 'DestinazioneBanner' ),
	'BannerController' => array( 'ControllerBanner' ),
	'BannerLoader' => array( 'CaricatoreBanner' ),
	'HideBanners' => array( 'NascondiBanner' ),
);

/** Tagalog (Tagalog) */
$specialPageAliases['tl'] = array(
	'CentralNotice' => array( 'Panggitnang_Pabatid' ),
	'CentralNoticeLogs' => array( 'Mga_Pagtatala_ng_Panggitnang_Pabatid' ),
	'NoticeTemplate' => array( 'Suleras_ng_Pabatid' ),
);

/** Turkish (Türkçe) */
$specialPageAliases['tr'] = array(
	'CentralNotice' => array( 'MerkeziBildirim' ),
	'CentralNoticeLogs' => array( 'MerkeziBildirimGünlükleri' ),
	'NoticeTemplate' => array( 'BildirimŞablonu' ),
	'BannerAllocation' => array( 'AfişTahsisi' ),
	'BannerController' => array( 'AfişKontrolü', 'AfişKontrolAracı' ),
	'BannerLoader' => array( 'AfişYükleyici' ),
	'HideBanners' => array( 'AfişleriGizle', 'AfişGizle' ),
);

/** Ukrainian (українська) */
$specialPageAliases['uk'] = array(
	'CentralNotice' => array( 'Загальне_оголошення' ),
);

/** Urdu (اردو) */
$specialPageAliases['ur'] = array(
	'CentralNotice' => array( 'مرکزی_اعلان' ),
);

/** Vietnamese (Tiếng Việt) */
$specialPageAliases['vi'] = array(
	'CentralNotice' => array( 'Thông_báo_chung' ),
	'CentralNoticeLogs' => array( 'Nhật_trình_thông_báo_chung' ),
	'NoticeTemplate' => array( 'Bản_mẫu_thông_báo' ),
	'GlobalAllocation' => array( 'Phân_phối_toàn_cục' ),
	'BannerAllocation' => array( 'Phân_bố_bảng' ),
	'BannerController' => array( 'Điều_khiển_bảng' ),
	'BannerLoader' => array( 'Tải_bảng' ),
	'BannerRandom' => array( 'Bảng_ngẫu_nhiên' ),
	'HideBanners' => array( 'Ẩn_bảng' ),
);

/** Simplified Chinese (中文（简体）‎) */
$specialPageAliases['zh-hans'] = array(
	'CentralNotice' => array( '中央通告' ),
	'CentralNoticeLogs' => array( '中央通告日志' ),
	'NoticeTemplate' => array( '公告模板' ),
	'BannerAllocation' => array( '横幅分配' ),
	'BannerController' => array( '横幅控制器' ),
	'BannerLoader' => array( '横幅装载器' ),
	'HideBanners' => array( '隐藏横幅' ),
);

/** Traditional Chinese (中文（繁體）‎) */
$specialPageAliases['zh-hant'] = array(
	'CentralNotice' => array( '中央通告' ),
	'CentralNoticeLogs' => array( '中央通告日誌' ),
	'NoticeTemplate' => array( '通告模板' ),
	'BannerAllocation' => array( '横幅分配' ),
	'BannerController' => array( '横幅控制器' ),
	'BannerLoader' => array( '橫幅裝載' ),
	'HideBanners' => array( '隱藏橫幅' ),
);