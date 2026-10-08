import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';

// ── Currencies ───────────────────────────────────────────────────────────────
// Prices from the backend are in PHP. `rate` = how many PHP equal 1 unit of the
// currency, so converted = phpAmount / rate. Rates are approximate.

export type CurrencyCode = string;

type CurrencyInfo = { code: string; symbol: string; name: string; rate: number; decimals: number };

const CURRENCY_DATA: CurrencyInfo[] = [
  { code: 'PHP', symbol: '\u20B1', name: 'Philippine Peso', rate: 1, decimals: 0 },
];

export const CURRENCIES: Record<string, CurrencyInfo> = Object.fromEntries(
  CURRENCY_DATA.map((c) => [c.code, c])
);

export const CURRENCY_LIST = CURRENCY_DATA;

// ── Languages ────────────────────────────────────────────────────────────────

export type LanguageCode = string;

export const LANGUAGES: { code: string; label: string; locale: string }[] = [
  { code: 'en', label: 'English (US)', locale: 'en-US' },
];

type Dict = Record<string, string>;

const TRANSLATIONS: Record<string, Dict> = {
  en: {
    tab_home: 'Home', tab_wishlist: 'Wishlist', tab_sale: 'Sale', tab_trips: 'Trips', tab_account: 'Account',
    account_title: 'Account', welcome_guest: 'Welcome to GuideMate',
    guest_sub: 'Sign in to book, save, and manage trips', signin_btn: 'Sign in / Register',
    logout: 'Log out', explore: 'Explore Cebu', bookings: 'My bookings', saved: 'Saved', settings: 'Settings',
    settings_title: 'Settings', sec_account: 'Account settings', login_methods: 'Login methods',
    account_security: 'Account security', fingerprint: 'Fingerprint', not_enabled: 'Not enabled', enabled: 'Enabled',
    sec_prefs: 'Preferences', language: 'Language', currency: 'Currency', notifications: 'Notification settings',
    sec_others: 'Others', feedback: 'Leave feedback', about: 'About',
    choose_language: 'Choose language', choose_currency: 'Choose currency', app_version: 'Version',
    search_ph: 'Search places in Cebu', recommended: 'Recommended in Cebu', see_all: 'See all', free: 'Free',
    show_original: 'Show original', show_translation: 'Show translation',
  },
  es: {
    tab_home: 'Inicio', tab_wishlist: 'Favoritos', tab_sale: 'Ofertas', tab_trips: 'Viajes', tab_account: 'Cuenta',
    account_title: 'Cuenta', welcome_guest: 'Bienvenido a GuideMate',
    guest_sub: 'Inicia sesión para reservar, guardar y gestionar viajes', signin_btn: 'Iniciar sesión / Registrarse',
    logout: 'Cerrar sesión', explore: 'Explorar Cebú', bookings: 'Mis reservas', saved: 'Guardado', settings: 'Configuración',
    settings_title: 'Configuración', sec_account: 'Configuración de la cuenta', login_methods: 'Métodos de inicio de sesión',
    account_security: 'Seguridad de la cuenta', fingerprint: 'Huella digital', not_enabled: 'No habilitado',
    sec_prefs: 'Preferencias', language: 'Idioma', currency: 'Moneda', notifications: 'Configuración de notificaciones',
    sec_others: 'Otros', feedback: 'Dejar comentarios', about: 'Acerca de',
    choose_language: 'Elegir idioma', choose_currency: 'Elegir moneda', app_version: 'Versión',
    search_ph: 'Buscar lugares en Cebú', recommended: 'Recomendado en Cebú', see_all: 'Ver todo', free: 'Gratis',
    show_original: 'Ver original', show_translation: 'Ver traducción',
  },
  fr: {
    tab_home: 'Accueil', tab_wishlist: 'Favoris', tab_sale: 'Offres', tab_trips: 'Voyages', tab_account: 'Compte',
    account_title: 'Compte', welcome_guest: 'Bienvenue sur GuideMate',
    guest_sub: 'Connectez-vous pour réserver, enregistrer et gérer vos voyages', signin_btn: 'Se connecter / S\u2019inscrire',
    logout: 'Se déconnecter', explore: 'Explorer Cebu', bookings: 'Mes réservations', saved: 'Enregistré', settings: 'Paramètres',
    settings_title: 'Paramètres', sec_account: 'Paramètres du compte', login_methods: 'Méthodes de connexion',
    account_security: 'Sécurité du compte', fingerprint: 'Empreinte digitale', not_enabled: 'Non activé',
    sec_prefs: 'Préférences', language: 'Langue', currency: 'Devise', notifications: 'Paramètres de notification',
    sec_others: 'Autres', feedback: 'Laisser un commentaire', about: 'À propos',
    choose_language: 'Choisir la langue', choose_currency: 'Choisir la devise', app_version: 'Version',
    search_ph: 'Rechercher des lieux à Cebu', recommended: 'Recommandé à Cebu', see_all: 'Tout voir', free: 'Gratuit',
    show_original: 'Voir l’original', show_translation: 'Voir la traduction',
  },
  de: {
    tab_home: 'Startseite', tab_wishlist: 'Merkliste', tab_sale: 'Angebote', tab_trips: 'Reisen', tab_account: 'Konto',
    account_title: 'Konto', welcome_guest: 'Willkommen bei GuideMate',
    guest_sub: 'Melde dich an, um zu buchen, zu speichern und Reisen zu verwalten', signin_btn: 'Anmelden / Registrieren',
    logout: 'Abmelden', explore: 'Cebu entdecken', bookings: 'Meine Buchungen', saved: 'Gespeichert', settings: 'Einstellungen',
    settings_title: 'Einstellungen', sec_account: 'Kontoeinstellungen', login_methods: 'Anmeldemethoden',
    account_security: 'Kontosicherheit', fingerprint: 'Fingerabdruck', not_enabled: 'Nicht aktiviert',
    sec_prefs: 'Präferenzen', language: 'Sprache', currency: 'Währung', notifications: 'Benachrichtigungseinstellungen',
    sec_others: 'Sonstiges', feedback: 'Feedback geben', about: 'Über',
    choose_language: 'Sprache wählen', choose_currency: 'Währung wählen', app_version: 'Version',
    search_ph: 'Orte in Cebu suchen', recommended: 'Empfohlen in Cebu', see_all: 'Alle ansehen', free: 'Kostenlos',
    show_original: 'Original anzeigen', show_translation: 'Übersetzung anzeigen',
  },
  it: {
    tab_home: 'Home', tab_wishlist: 'Preferiti', tab_sale: 'Offerte', tab_trips: 'Viaggi', tab_account: 'Account',
    account_title: 'Account', welcome_guest: 'Benvenuto su GuideMate',
    guest_sub: 'Accedi per prenotare, salvare e gestire i viaggi', signin_btn: 'Accedi / Registrati',
    logout: 'Esci', explore: 'Esplora Cebu', bookings: 'Le mie prenotazioni', saved: 'Salvati', settings: 'Impostazioni',
    settings_title: 'Impostazioni', sec_account: 'Impostazioni account', login_methods: 'Metodi di accesso',
    account_security: 'Sicurezza account', fingerprint: 'Impronta digitale', not_enabled: 'Non abilitato',
    sec_prefs: 'Preferenze', language: 'Lingua', currency: 'Valuta', notifications: 'Impostazioni notifiche',
    sec_others: 'Altro', feedback: 'Lascia un feedback', about: 'Informazioni',
    choose_language: 'Scegli la lingua', choose_currency: 'Scegli la valuta', app_version: 'Versione',
    search_ph: 'Cerca luoghi a Cebu', recommended: 'Consigliato a Cebu', see_all: 'Vedi tutto', free: 'Gratis',
    show_original: 'Mostra originale', show_translation: 'Mostra traduzione',
  },
  pt: {
    tab_home: 'Início', tab_wishlist: 'Favoritos', tab_sale: 'Ofertas', tab_trips: 'Viagens', tab_account: 'Conta',
    account_title: 'Conta', welcome_guest: 'Bem-vindo ao GuideMate',
    guest_sub: 'Entre para reservar, salvar e gerenciar viagens', signin_btn: 'Entrar / Registrar',
    logout: 'Sair', explore: 'Explorar Cebu', bookings: 'Minhas reservas', saved: 'Salvos', settings: 'Configurações',
    settings_title: 'Configurações', sec_account: 'Configurações da conta', login_methods: 'Métodos de login',
    account_security: 'Segurança da conta', fingerprint: 'Impressão digital', not_enabled: 'Não ativado',
    sec_prefs: 'Preferências', language: 'Idioma', currency: 'Moeda', notifications: 'Configurações de notificação',
    sec_others: 'Outros', feedback: 'Deixar feedback', about: 'Sobre',
    choose_language: 'Escolher idioma', choose_currency: 'Escolher moeda', app_version: 'Versão',
    search_ph: 'Buscar lugares em Cebu', recommended: 'Recomendado em Cebu', see_all: 'Ver tudo', free: 'Grátis',
    show_original: 'Ver original', show_translation: 'Ver tradução',
  },
  nl: {
    tab_home: 'Home', tab_wishlist: 'Verlanglijst', tab_sale: 'Aanbiedingen', tab_trips: 'Reizen', tab_account: 'Account',
    account_title: 'Account', welcome_guest: 'Welkom bij GuideMate',
    guest_sub: 'Log in om te boeken, op te slaan en reizen te beheren', signin_btn: 'Inloggen / Registreren',
    logout: 'Uitloggen', explore: 'Ontdek Cebu', bookings: 'Mijn boekingen', saved: 'Opgeslagen', settings: 'Instellingen',
    settings_title: 'Instellingen', sec_account: 'Accountinstellingen', login_methods: 'Inlogmethoden',
    account_security: 'Accountbeveiliging', fingerprint: 'Vingerafdruk', not_enabled: 'Niet ingeschakeld',
    sec_prefs: 'Voorkeuren', language: 'Taal', currency: 'Valuta', notifications: 'Meldingsinstellingen',
    sec_others: 'Overig', feedback: 'Feedback geven', about: 'Over',
    choose_language: 'Kies taal', choose_currency: 'Kies valuta', app_version: 'Versie',
    search_ph: 'Zoek plaatsen in Cebu', recommended: 'Aanbevolen in Cebu', see_all: 'Alles bekijken', free: 'Gratis',
    show_original: 'Origineel tonen', show_translation: 'Vertaling tonen',
  },
  ru: {
    tab_home: 'Главная', tab_wishlist: 'Избранное', tab_sale: 'Скидки', tab_trips: 'Поездки', tab_account: 'Аккаунт',
    account_title: 'Аккаунт', welcome_guest: 'Добро пожаловать в GuideMate',
    guest_sub: 'Войдите, чтобы бронировать, сохранять и управлять поездками', signin_btn: 'Войти / Регистрация',
    logout: 'Выйти', explore: 'Исследовать Себу', bookings: 'Мои брони', saved: 'Сохранённое', settings: 'Настройки',
    settings_title: 'Настройки', sec_account: 'Настройки аккаунта', login_methods: 'Способы входа',
    account_security: 'Безопасность аккаунта', fingerprint: 'Отпечаток пальца', not_enabled: 'Не включено',
    sec_prefs: 'Предпочтения', language: 'Язык', currency: 'Валюта', notifications: 'Настройки уведомлений',
    sec_others: 'Другое', feedback: 'Оставить отзыв', about: 'О приложении',
    choose_language: 'Выбрать язык', choose_currency: 'Выбрать валюту', app_version: 'Версия',
    search_ph: 'Поиск мест в Себу', recommended: 'Рекомендуемое в Себу', see_all: 'Показать все', free: 'Бесплатно',
    show_original: 'Показать оригинал', show_translation: 'Показать перевод',
  },
  tr: {
    tab_home: 'Ana sayfa', tab_wishlist: 'İstek listesi', tab_sale: 'İndirimler', tab_trips: 'Geziler', tab_account: 'Hesap',
    account_title: 'Hesap', welcome_guest: 'GuideMate\u2019e hoş geldiniz',
    guest_sub: 'Rezervasyon yapmak, kaydetmek ve gezileri yönetmek için giriş yapın', signin_btn: 'Giriş yap / Kayıt ol',
    logout: 'Çıkış yap', explore: 'Cebu\u2019yu keşfet', bookings: 'Rezervasyonlarım', saved: 'Kaydedildi', settings: 'Ayarlar',
    settings_title: 'Ayarlar', sec_account: 'Hesap ayarları', login_methods: 'Giriş yöntemleri',
    account_security: 'Hesap güvenliği', fingerprint: 'Parmak izi', not_enabled: 'Etkin değil',
    sec_prefs: 'Tercihler', language: 'Dil', currency: 'Para birimi', notifications: 'Bildirim ayarları',
    sec_others: 'Diğer', feedback: 'Geri bildirim gönder', about: 'Hakkında',
    choose_language: 'Dil seçin', choose_currency: 'Para birimi seçin', app_version: 'Sürüm',
    search_ph: 'Cebu\u2019da yer ara', recommended: 'Cebu\u2019da önerilen', see_all: 'Tümünü gör', free: 'Ücretsiz',
    show_original: 'Orijinali göster', show_translation: 'Çeviriyi göster',
  },
  ar: {
    tab_home: 'الرئيسية', tab_wishlist: 'المفضلة', tab_sale: 'عروض', tab_trips: 'الرحلات', tab_account: 'الحساب',
    account_title: 'الحساب', welcome_guest: 'مرحبًا بك في GuideMate',
    guest_sub: 'سجّل الدخول للحجز والحفظ وإدارة الرحلات', signin_btn: 'تسجيل الدخول / إنشاء حساب',
    logout: 'تسجيل الخروج', explore: 'استكشف سيبو', bookings: 'حجوزاتي', saved: 'المحفوظات', settings: 'الإعدادات',
    settings_title: 'الإعدادات', sec_account: 'إعدادات الحساب', login_methods: 'طرق تسجيل الدخول',
    account_security: 'أمان الحساب', fingerprint: 'بصمة الإصبع', not_enabled: 'غير مُفعّل',
    sec_prefs: 'التفضيلات', language: 'اللغة', currency: 'العملة', notifications: 'إعدادات الإشعارات',
    sec_others: 'أخرى', feedback: 'إرسال ملاحظات', about: 'حول',
    choose_language: 'اختر اللغة', choose_currency: 'اختر العملة', app_version: 'الإصدار',
    search_ph: 'ابحث عن أماكن في سيبو', recommended: 'موصى به في سيبو', see_all: 'عرض الكل', free: 'مجاني',
    show_original: 'إظهار الأصل', show_translation: 'إظهار الترجمة',
  },
  hi: {
    tab_home: 'होम', tab_wishlist: 'इच्छा-सूची', tab_sale: 'सेल', tab_trips: 'यात्राएँ', tab_account: 'खाता',
    account_title: 'खाता', welcome_guest: 'GuideMate में आपका स्वागत है',
    guest_sub: 'बुक करने, सहेजने और यात्राएँ प्रबंधित करने के लिए साइन इन करें', signin_btn: 'साइन इन / रजिस्टर',
    logout: 'लॉग आउट', explore: 'सेबू एक्सप्लोर करें', bookings: 'मेरी बुकिंग', saved: 'सहेजा गया', settings: 'सेटिंग्स',
    settings_title: 'सेटिंग्स', sec_account: 'खाता सेटिंग्स', login_methods: 'लॉगिन विधियाँ',
    account_security: 'खाता सुरक्षा', fingerprint: 'फ़िंगरप्रिंट', not_enabled: 'सक्षम नहीं',
    sec_prefs: 'प्राथमिकताएँ', language: 'भाषा', currency: 'मुद्रा', notifications: 'सूचना सेटिंग्स',
    sec_others: 'अन्य', feedback: 'प्रतिक्रिया दें', about: 'परिचय',
    choose_language: 'भाषा चुनें', choose_currency: 'मुद्रा चुनें', app_version: 'संस्करण',
    search_ph: 'सेबू में स्थान खोजें', recommended: 'सेबू में अनुशंसित', see_all: 'सभी देखें', free: 'निःशुल्क',
    show_original: 'मूल दिखाएं', show_translation: 'अनुवाद दिखाएं',
  },
  id: {
    tab_home: 'Beranda', tab_wishlist: 'Favorit', tab_sale: 'Promo', tab_trips: 'Perjalanan', tab_account: 'Akun',
    account_title: 'Akun', welcome_guest: 'Selamat datang di GuideMate',
    guest_sub: 'Masuk untuk memesan, menyimpan, dan mengelola perjalanan', signin_btn: 'Masuk / Daftar',
    logout: 'Keluar', explore: 'Jelajahi Cebu', bookings: 'Pesanan saya', saved: 'Tersimpan', settings: 'Pengaturan',
    settings_title: 'Pengaturan', sec_account: 'Pengaturan akun', login_methods: 'Metode masuk',
    account_security: 'Keamanan akun', fingerprint: 'Sidik jari', not_enabled: 'Tidak aktif',
    sec_prefs: 'Preferensi', language: 'Bahasa', currency: 'Mata uang', notifications: 'Pengaturan notifikasi',
    sec_others: 'Lainnya', feedback: 'Beri masukan', about: 'Tentang',
    choose_language: 'Pilih bahasa', choose_currency: 'Pilih mata uang', app_version: 'Versi',
    search_ph: 'Cari tempat di Cebu', recommended: 'Direkomendasikan di Cebu', see_all: 'Lihat semua', free: 'Gratis',
    show_original: 'Tampilkan asli', show_translation: 'Tampilkan terjemahan',
  },
  th: {
    tab_home: 'หน้าแรก', tab_wishlist: 'รายการโปรด', tab_sale: 'โปรโมชั่น', tab_trips: 'ทริป', tab_account: 'บัญชี',
    account_title: 'บัญชี', welcome_guest: 'ยินดีต้อนรับสู่ GuideMate',
    guest_sub: 'ลงชื่อเข้าใช้เพื่อจอง บันทึก และจัดการทริป', signin_btn: 'เข้าสู่ระบบ / ลงทะเบียน',
    logout: 'ออกจากระบบ', explore: 'สำรวจเซบู', bookings: 'การจองของฉัน', saved: 'บันทึกแล้ว', settings: 'การตั้งค่า',
    settings_title: 'การตั้งค่า', sec_account: 'การตั้งค่าบัญชี', login_methods: 'วิธีเข้าสู่ระบบ',
    account_security: 'ความปลอดภัยบัญชี', fingerprint: 'ลายนิ้วมือ', not_enabled: 'ยังไม่เปิดใช้งาน',
    sec_prefs: 'การกำหนดลักษณะ', language: 'ภาษา', currency: 'สกุลเงิน', notifications: 'การตั้งค่าการแจ้งเตือน',
    sec_others: 'อื่นๆ', feedback: 'ส่งความคิดเห็น', about: 'เกี่ยวกับ',
    choose_language: 'เลือกภาษา', choose_currency: 'เลือกสกุลเงิน', app_version: 'เวอร์ชัน',
    search_ph: 'ค้นหาสถานที่ในเซบู', recommended: 'แนะนำในเซบู', see_all: 'ดูทั้งหมด', free: 'ฟรี',
    show_original: 'ดูต้นฉบับ', show_translation: 'ดูคำแปล',
  },
  vi: {
    tab_home: 'Trang chủ', tab_wishlist: 'Yêu thích', tab_sale: 'Khuyến mãi', tab_trips: 'Chuyến đi', tab_account: 'Tài khoản',
    account_title: 'Tài khoản', welcome_guest: 'Chào mừng đến với GuideMate',
    guest_sub: 'Đăng nhập để đặt, lưu và quản lý chuyến đi', signin_btn: 'Đăng nhập / Đăng ký',
    logout: 'Đăng xuất', explore: 'Khám phá Cebu', bookings: 'Đặt chỗ của tôi', saved: 'Đã lưu', settings: 'Cài đặt',
    settings_title: 'Cài đặt', sec_account: 'Cài đặt tài khoản', login_methods: 'Phương thức đăng nhập',
    account_security: 'Bảo mật tài khoản', fingerprint: 'Vân tay', not_enabled: 'Chưa bật',
    sec_prefs: 'Tùy chọn', language: 'Ngôn ngữ', currency: 'Tiền tệ', notifications: 'Cài đặt thông báo',
    sec_others: 'Khác', feedback: 'Gửi phản hồi', about: 'Giới thiệu',
    choose_language: 'Chọn ngôn ngữ', choose_currency: 'Chọn tiền tệ', app_version: 'Phiên bản',
    search_ph: 'Tìm địa điểm ở Cebu', recommended: 'Đề xuất ở Cebu', see_all: 'Xem tất cả', free: 'Miễn phí',
    show_original: 'Xem bản gốc', show_translation: 'Xem bản dịch',
  },
  fil: {
    tab_home: 'Home', tab_wishlist: 'Wishlist', tab_sale: 'Sale', tab_trips: 'Mga Biyahe', tab_account: 'Account',
    account_title: 'Account', welcome_guest: 'Maligayang pagdating sa GuideMate',
    guest_sub: 'Mag-sign in para mag-book, mag-save, at pamahalaan ang mga biyahe', signin_btn: 'Mag-sign in / Magrehistro',
    logout: 'Mag-log out', explore: 'Tuklasin ang Cebu', bookings: 'Aking mga booking', saved: 'Naka-save', settings: 'Mga Setting',
    settings_title: 'Mga Setting', sec_account: 'Mga setting ng account', login_methods: 'Mga paraan ng pag-login',
    account_security: 'Seguridad ng account', fingerprint: 'Fingerprint', not_enabled: 'Hindi naka-enable',
    sec_prefs: 'Mga Kagustuhan', language: 'Wika', currency: 'Pera', notifications: 'Mga setting ng notification',
    sec_others: 'Iba pa', feedback: 'Mag-iwan ng feedback', about: 'Tungkol',
    choose_language: 'Pumili ng wika', choose_currency: 'Pumili ng pera', app_version: 'Bersyon',
    search_ph: 'Maghanap ng lugar sa Cebu', recommended: 'Inirerekomenda sa Cebu', see_all: 'Tingnan lahat', free: 'Libre',
    show_original: 'Ipakita ang orihinal', show_translation: 'Ipakita ang salin',
  },
  zh: {
    tab_home: '首页', tab_wishlist: '收藏', tab_sale: '特惠', tab_trips: '行程', tab_account: '账户',
    account_title: '账户', welcome_guest: '欢迎使用 GuideMate',
    guest_sub: '登录以预订、收藏和管理行程', signin_btn: '登录 / 注册',
    logout: '退出登录', explore: '探索宿务', bookings: '我的预订', saved: '已收藏', settings: '设置',
    settings_title: '设置', sec_account: '账户设置', login_methods: '登录方式',
    account_security: '账户安全', fingerprint: '指纹', not_enabled: '未启用',
    sec_prefs: '偏好设置', language: '语言', currency: '货币', notifications: '通知设置',
    sec_others: '其他', feedback: '提交反馈', about: '关于',
    choose_language: '选择语言', choose_currency: '选择货币', app_version: '版本',
    search_ph: '搜索宿务的景点', recommended: '宿务推荐', see_all: '查看全部', free: '免费',
    show_original: '显示原文', show_translation: '显示翻译',
  },
  'zh-Hant': {
    tab_home: '首頁', tab_wishlist: '收藏', tab_sale: '特惠', tab_trips: '行程', tab_account: '帳戶',
    account_title: '帳戶', welcome_guest: '歡迎使用 GuideMate',
    guest_sub: '登入以預訂、收藏和管理行程', signin_btn: '登入 / 註冊',
    logout: '登出', explore: '探索宿霧', bookings: '我的預訂', saved: '已收藏', settings: '設定',
    settings_title: '設定', sec_account: '帳戶設定', login_methods: '登入方式',
    account_security: '帳戶安全', fingerprint: '指紋', not_enabled: '未啟用',
    sec_prefs: '偏好設定', language: '語言', currency: '貨幣', notifications: '通知設定',
    sec_others: '其他', feedback: '提交意見', about: '關於',
    choose_language: '選擇語言', choose_currency: '選擇貨幣', app_version: '版本',
    search_ph: '搜尋宿霧的景點', recommended: '宿霧推薦', see_all: '查看全部', free: '免費',
    show_original: '顯示原文', show_translation: '顯示翻譯',
  },
  ja: {
    tab_home: 'ホーム', tab_wishlist: 'お気に入り', tab_sale: 'セール', tab_trips: '旅行', tab_account: 'アカウント',
    account_title: 'アカウント', welcome_guest: 'GuideMateへようこそ',
    guest_sub: '予約・保存・旅行管理にはログインしてください', signin_btn: 'ログイン / 登録',
    logout: 'ログアウト', explore: 'セブを探索', bookings: '予約一覧', saved: '保存済み', settings: '設定',
    settings_title: '設定', sec_account: 'アカウント設定', login_methods: 'ログイン方法',
    account_security: 'アカウントセキュリティ', fingerprint: '指紋', not_enabled: '無効',
    sec_prefs: '環境設定', language: '言語', currency: '通貨', notifications: '通知設定',
    sec_others: 'その他', feedback: 'フィードバックを送る', about: 'アプリについて',
    choose_language: '言語を選択', choose_currency: '通貨を選択', app_version: 'バージョン',
    search_ph: 'セブの場所を検索', recommended: 'セブのおすすめ', see_all: 'すべて見る', free: '無料',
    show_original: '原文を表示', show_translation: '翻訳を表示',
  },
  ko: {
    tab_home: '홈', tab_wishlist: '위시리스트', tab_sale: '세일', tab_trips: '여행', tab_account: '계정',
    account_title: '계정', welcome_guest: 'GuideMate에 오신 것을 환영합니다',
    guest_sub: '예약, 저장 및 여행 관리를 위해 로그인하세요', signin_btn: '로그인 / 회원가입',
    logout: '로그아웃', explore: '세부 둘러보기', bookings: '내 예약', saved: '저장됨', settings: '설정',
    settings_title: '설정', sec_account: '계정 설정', login_methods: '로그인 방법',
    account_security: '계정 보안', fingerprint: '지문', not_enabled: '사용 안 함',
    sec_prefs: '환경설정', language: '언어', currency: '통화', notifications: '알림 설정',
    sec_others: '기타', feedback: '피드백 남기기', about: '정보',
    choose_language: '언어 선택', choose_currency: '통화 선택', app_version: '버전',
    search_ph: '세부에서 장소 검색', recommended: '세부 추천', see_all: '모두 보기', free: '무료',
    show_original: '원문 보기', show_translation: '번역 보기',
  },
};

// Login / auth screen strings, merged into TRANSLATIONS below so the whole login
// page follows the selected language.
const LOGIN_I18N: Record<string, Dict> = {
  en: { login_title: 'Log In', login_welcome: 'Welcome back, explorer.', login_subtitle: 'Sign in to continue your journey.', login_as: 'Log in as', login_fp: 'Log in with fingerprint', or_password: 'or use password', email_ph: 'Email address', password_ph: 'Password', verify_human: 'Verify you are human', login_btn: 'Login', signing_in: 'Signing in...', forgot_pw: 'Forgot Password?', no_account: "Don't have an account? ", sign_up: '', continue_guest: 'Continue as guest', or_continue_with: 'or continue with', continue_google: 'Continue with Google', continue_facebook: 'Continue with Facebook' },
  es: { login_title: 'Iniciar sesión', login_welcome: 'Bienvenido de nuevo, explorador.', login_subtitle: 'Inicia sesión para continuar tu viaje.', login_as: 'Iniciar sesión como', login_fp: 'Iniciar sesión con huella', or_password: 'o usa tu contraseña', email_ph: 'Correo electrónico', password_ph: 'Contraseña', verify_human: 'Verifica que eres humano', login_btn: 'Iniciar sesión', signing_in: 'Iniciando sesión...', forgot_pw: '¿Olvidaste tu contraseña?', no_account: '¿No tienes una cuenta? ', sign_up: 'Regístrate', continue_guest: 'Continuar como invitado' },
  fr: { login_title: 'Connexion', login_welcome: 'Bon retour, explorateur.', login_subtitle: 'Connectez-vous pour continuer votre voyage.', login_as: 'Se connecter en tant que', login_fp: "Se connecter avec l'empreinte", or_password: 'ou utilisez le mot de passe', email_ph: 'Adresse e-mail', password_ph: 'Mot de passe', verify_human: 'Vérifiez que vous êtes humain', login_btn: 'Se connecter', signing_in: 'Connexion...', forgot_pw: 'Mot de passe oublié ?', no_account: "Vous n'avez pas de compte ? ", sign_up: "S'inscrire", continue_guest: 'Continuer en tant qu’invité' },
  de: { login_title: 'Anmelden', login_welcome: 'Willkommen zurück, Entdecker.', login_subtitle: 'Melde dich an, um deine Reise fortzusetzen.', login_as: 'Anmelden als', login_fp: 'Mit Fingerabdruck anmelden', or_password: 'oder Passwort verwenden', email_ph: 'E-Mail-Adresse', password_ph: 'Passwort', verify_human: 'Bestätige, dass du ein Mensch bist', login_btn: 'Anmelden', signing_in: 'Anmeldung...', forgot_pw: 'Passwort vergessen?', no_account: 'Noch kein Konto? ', sign_up: 'Registrieren', continue_guest: 'Als Gast fortfahren' },
  it: { login_title: 'Accedi', login_welcome: 'Bentornato, esploratore.', login_subtitle: 'Accedi per continuare il tuo viaggio.', login_as: 'Accedi come', login_fp: "Accedi con l'impronta", or_password: 'o usa la password', email_ph: 'Indirizzo email', password_ph: 'Password', verify_human: 'Verifica di essere umano', login_btn: 'Accedi', signing_in: 'Accesso in corso...', forgot_pw: 'Password dimenticata?', no_account: 'Non hai un account? ', sign_up: 'Registrati', continue_guest: 'Continua come ospite' },
  pt: { login_title: 'Entrar', login_welcome: 'Bem-vindo de volta, explorador.', login_subtitle: 'Entre para continuar sua viagem.', login_as: 'Entrar como', login_fp: 'Entrar com impressão digital', or_password: 'ou use a senha', email_ph: 'Endereço de e-mail', password_ph: 'Senha', verify_human: 'Verifique que você é humano', login_btn: 'Entrar', signing_in: 'Entrando...', forgot_pw: 'Esqueceu a senha?', no_account: 'Não tem uma conta? ', sign_up: 'Cadastre-se', continue_guest: 'Continuar como convidado' },
  nl: { login_title: 'Inloggen', login_welcome: 'Welkom terug, ontdekker.', login_subtitle: 'Log in om je reis voort te zetten.', login_as: 'Inloggen als', login_fp: 'Inloggen met vingerafdruk', or_password: 'of gebruik wachtwoord', email_ph: 'E-mailadres', password_ph: 'Wachtwoord', verify_human: 'Bevestig dat je een mens bent', login_btn: 'Inloggen', signing_in: 'Bezig met inloggen...', forgot_pw: 'Wachtwoord vergeten?', no_account: 'Nog geen account? ', sign_up: 'Registreren', continue_guest: 'Doorgaan als gast' },
  ru: { login_title: 'Вход', login_welcome: 'С возвращением, путешественник.', login_subtitle: 'Войдите, чтобы продолжить путешествие.', login_as: 'Войти как', login_fp: 'Войти по отпечатку пальца', or_password: 'или введите пароль', email_ph: 'Электронная почта', password_ph: 'Пароль', verify_human: 'Подтвердите, что вы человек', login_btn: 'Войти', signing_in: 'Вход...', forgot_pw: 'Забыли пароль?', no_account: 'Нет аккаунта? ', sign_up: 'Зарегистрироваться', continue_guest: 'Продолжить как гость' },
  tr: { login_title: 'Giriş yap', login_welcome: 'Tekrar hoş geldin, kâşif.', login_subtitle: 'Yolculuğuna devam etmek için giriş yap.', login_as: 'Giriş yap:', login_fp: 'Parmak iziyle giriş yap', or_password: 'veya şifre kullan', email_ph: 'E-posta adresi', password_ph: 'Şifre', verify_human: 'İnsan olduğunu doğrula', login_btn: 'Giriş yap', signing_in: 'Giriş yapılıyor...', forgot_pw: 'Şifreni mi unuttun?', no_account: 'Hesabın yok mu? ', sign_up: 'Kaydol', continue_guest: 'Misafir olarak devam et' },
  ar: { login_title: 'تسجيل الدخول', login_welcome: 'مرحبًا بعودتك أيها المستكشف.', login_subtitle: 'سجّل الدخول لمواصلة رحلتك.', login_as: 'تسجيل الدخول باسم', login_fp: 'تسجيل الدخول ببصمة الإصبع', or_password: 'أو استخدم كلمة المرور', email_ph: 'البريد الإلكتروني', password_ph: 'كلمة المرور', verify_human: 'تحقق من أنك إنسان', login_btn: 'تسجيل الدخول', signing_in: 'جارٍ تسجيل الدخول...', forgot_pw: 'هل نسيت كلمة المرور؟', no_account: 'ليس لديك حساب؟ ', sign_up: 'إنشاء حساب', continue_guest: 'المتابعة كضيف' },
  hi: { login_title: 'लॉग इन', login_welcome: 'वापसी पर स्वागत है, खोजी।', login_subtitle: 'अपनी यात्रा जारी रखने के लिए साइन इन करें।', login_as: 'इस रूप में लॉग इन करें:', login_fp: 'फ़िंगरप्रिंट से लॉग इन करें', or_password: 'या पासवर्ड का उपयोग करें', email_ph: 'ईमेल पता', password_ph: 'पासवर्ड', verify_human: 'सत्यापित करें कि आप मानव हैं', login_btn: 'लॉग इन', signing_in: 'साइन इन हो रहा है...', forgot_pw: 'पासवर्ड भूल गए?', no_account: 'खाता नहीं है? ', sign_up: 'साइन अप करें', continue_guest: 'अतिथि के रूप में जारी रखें' },
  id: { login_title: 'Masuk', login_welcome: 'Selamat datang kembali, penjelajah.', login_subtitle: 'Masuk untuk melanjutkan perjalananmu.', login_as: 'Masuk sebagai', login_fp: 'Masuk dengan sidik jari', or_password: 'atau gunakan kata sandi', email_ph: 'Alamat email', password_ph: 'Kata sandi', verify_human: 'Verifikasi bahwa Anda manusia', login_btn: 'Masuk', signing_in: 'Sedang masuk...', forgot_pw: 'Lupa kata sandi?', no_account: 'Belum punya akun? ', sign_up: 'Daftar', continue_guest: 'Lanjutkan sebagai tamu' },
  th: { login_title: 'เข้าสู่ระบบ', login_welcome: 'ยินดีต้อนรับกลับ นักสำรวจ', login_subtitle: 'เข้าสู่ระบบเพื่อเดินทางต่อ', login_as: 'เข้าสู่ระบบในชื่อ', login_fp: 'เข้าสู่ระบบด้วยลายนิ้วมือ', or_password: 'หรือใช้รหัสผ่าน', email_ph: 'อีเมล', password_ph: 'รหัสผ่าน', verify_human: 'ยืนยันว่าคุณเป็นมนุษย์', login_btn: 'เข้าสู่ระบบ', signing_in: 'กำลังเข้าสู่ระบบ...', forgot_pw: 'ลืมรหัสผ่าน?', no_account: 'ยังไม่มีบัญชี? ', sign_up: 'สมัครสมาชิก', continue_guest: 'ดำเนินการต่อในฐานะผู้เยี่ยมชม' },
  vi: { login_title: 'Đăng nhập', login_welcome: 'Chào mừng trở lại, nhà thám hiểm.', login_subtitle: 'Đăng nhập để tiếp tục hành trình của bạn.', login_as: 'Đăng nhập với tên', login_fp: 'Đăng nhập bằng vân tay', or_password: 'hoặc dùng mật khẩu', email_ph: 'Địa chỉ email', password_ph: 'Mật khẩu', verify_human: 'Xác minh bạn là con người', login_btn: 'Đăng nhập', signing_in: 'Đang đăng nhập...', forgot_pw: 'Quên mật khẩu?', no_account: 'Chưa có tài khoản? ', sign_up: 'Đăng ký', continue_guest: 'Tiếp tục với tư cách khách' },
  fil: { login_title: 'Mag-log in', login_welcome: 'Maligayang pagbabalik, explorer.', login_subtitle: 'Mag-sign in para ipagpatuloy ang iyong paglalakbay.', login_as: 'Mag-log in bilang', login_fp: 'Mag-log in gamit ang fingerprint', or_password: 'o gamitin ang password', email_ph: 'Email address', password_ph: 'Password', verify_human: 'Patunayan na ikaw ay tao', login_btn: 'Mag-log in', signing_in: 'Nagla-log in...', forgot_pw: 'Nakalimutan ang password?', no_account: 'Wala pang account? ', sign_up: 'Mag-', continue_guest: 'Magpatuloy bilang bisita', or_continue_with: 'o magpatuloy gamit ang', continue_google: 'Magpatuloy gamit ang Google', continue_facebook: 'Magpatuloy gamit ang Facebook' },
  zh: { login_title: '登录', login_welcome: '欢迎回来，探索者。', login_subtitle: '登录以继续您的旅程。', login_as: '登录为', login_fp: '使用指纹登录', or_password: '或使用密码', email_ph: '电子邮箱', password_ph: '密码', verify_human: '验证您是真人', login_btn: '登录', signing_in: '正在登录...', forgot_pw: '忘记密码？', no_account: '还没有账户？ ', sign_up: '注册', continue_guest: '以访客身份继续' },
  'zh-Hant': { login_title: '登入', login_welcome: '歡迎回來，探索者。', login_subtitle: '登入以繼續您的旅程。', login_as: '登入為', login_fp: '使用指紋登入', or_password: '或使用密碼', email_ph: '電子郵件', password_ph: '密碼', verify_human: '驗證您是真人', login_btn: '登入', signing_in: '正在登入...', forgot_pw: '忘記密碼？', no_account: '還沒有帳戶？ ', sign_up: '註冊', continue_guest: '以訪客身分繼續' },
  ja: { login_title: 'ログイン', login_welcome: 'おかえりなさい、探検者。', login_subtitle: '旅を続けるにはサインインしてください。', login_as: 'ログイン：', login_fp: '指紋でログイン', or_password: 'またはパスワードを使用', email_ph: 'メールアドレス', password_ph: 'パスワード', verify_human: '人間であることを確認', login_btn: 'ログイン', signing_in: 'サインイン中...', forgot_pw: 'パスワードをお忘れですか？', no_account: 'アカウントをお持ちでないですか？ ', sign_up: '新規登録', continue_guest: 'ゲストとして続ける' },
  ko: { login_title: '로그인', login_welcome: '다시 오신 것을 환영합니다, 탐험가님.', login_subtitle: '여정을 계속하려면 로그인하세요.', login_as: '다음으로 로그인:', login_fp: '지문으로 로그인', or_password: '또는 비밀번호 사용', email_ph: '이메일 주소', password_ph: '비밀번호', verify_human: '사람인지 확인하세요', login_btn: '로그인', signing_in: '로그인 중...', forgot_pw: '비밀번호를 잊으셨나요?', no_account: '계정이 없으신가요? ', sign_up: '가입하기', continue_guest: '게스트로 계속하기' },
};

for (const code of Object.keys(LOGIN_I18N)) {
  TRANSLATIONS[code] = { ...(TRANSLATIONS[code] ?? {}), ...LOGIN_I18N[code] };
}

// ── Context ──────────────────────────────────────────────────────────────────

type PreferencesContextValue = {
  language: LanguageCode;
  currency: CurrencyCode;
  ready: boolean;
  setLanguage: (code: LanguageCode) => void;
  setCurrency: (code: CurrencyCode) => void;
  t: (key: string) => string;
  formatPrice: (phpAmount: number) => string;
};

const LANG_KEY = 'guidemate_language';
const CUR_KEY = 'guidemate_currency';

const PreferencesContext = createContext<PreferencesContextValue | undefined>(undefined);

export function PreferencesProvider({ children }: { children: React.ReactNode }) {
  const [language, setLanguageState] = useState<LanguageCode>('en');
  const [currency, setCurrencyState] = useState<CurrencyCode>('PHP');
  const [ready, setReady] = useState(false);

  useEffect(() => {
    (async () => {
      try {
        const [lang, cur] = await Promise.all([
          AsyncStorage.getItem(LANG_KEY),
          AsyncStorage.getItem(CUR_KEY),
        ]);
        const allowedLang = lang && LANGUAGES.some((l) => l.code === lang) ? lang : 'en';
        const allowedCur = cur && cur in CURRENCIES ? cur : 'PHP';
        setLanguageState(allowedLang);
        setCurrencyState(allowedCur);
        if (lang !== allowedLang) {
          AsyncStorage.setItem(LANG_KEY, allowedLang).catch(() => {});
        }
        if (cur !== allowedCur) {
          AsyncStorage.setItem(CUR_KEY, allowedCur).catch(() => {});
        }
      } catch {
        // ignore
      } finally {
        setReady(true);
      }
    })();
  }, []);

  const setLanguage = useCallback((code: LanguageCode) => {
    setLanguageState(code);
    AsyncStorage.setItem(LANG_KEY, code).catch(() => {});
  }, []);

  const setCurrency = useCallback((code: CurrencyCode) => {
    setCurrencyState(code);
    AsyncStorage.setItem(CUR_KEY, code).catch(() => {});
  }, []);

  const t = useCallback(
    (key: string) => TRANSLATIONS[language]?.[key] ?? TRANSLATIONS.en[key] ?? key,
    [language]
  );

  const formatPrice = useCallback(
    (phpAmount: number) => {
      const info = CURRENCIES[currency] ?? CURRENCIES.PHP;
      const locale = LANGUAGES.find((l) => l.code === language)?.locale ?? 'en-US';
      const value = phpAmount / info.rate;
      const formatted = value.toLocaleString(locale, {
        maximumFractionDigits: info.decimals,
        minimumFractionDigits: 0,
      });
      return `${info.symbol}${formatted}`;
    },
    [currency, language]
  );

  const value = useMemo(
    () => ({ language, currency, ready, setLanguage, setCurrency, t, formatPrice }),
    [language, currency, ready, setLanguage, setCurrency, t, formatPrice]
  );

  return <PreferencesContext.Provider value={value}>{children}</PreferencesContext.Provider>;
}

export function usePreferences(): PreferencesContextValue {
  const ctx = useContext(PreferencesContext);
  if (!ctx) {
    throw new Error('usePreferences must be used within a PreferencesProvider');
  }
  return ctx;
}
