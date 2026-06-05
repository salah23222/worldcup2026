<?php
/**
 * AccountMail.php — إيميلات الحسابات بهوية موحّدة (ترحيب + استعادة كلمة السر).
 * ============================================================
 * HTML نظيف يعمل في كل عملاء البريد + نسخة نصّية احتياطية.
 * بصيغة inline (لا CSS خارجي — يحجبه عملاء البريد).
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class AccountMail
{
    /** يبني الإطار الموحّد (Header بشارة 26 بيضاء + Footer). $inner = HTML المحتوى. */
    private static function shell(string $inner, string $lang = 'ar'): string
    {
        $ar   = ($lang === 'ar');
        $fr   = ($lang === 'fr');
        $dir  = $ar ? 'rtl' : 'ltr';
        $site = match($lang) {
            'ar' => defined('SITE_NAME_AR') ? SITE_NAME_AR : 'كأس العالم 2026',
            'fr' => defined('SITE_NAME_FR') ? SITE_NAME_FR : 'Coupe du Monde 2026',
            default => defined('SITE_NAME_EN') ? SITE_NAME_EN : 'World Cup 2026',
        };
        $url  = defined('SITE_URL') ? rtrim((string)SITE_URL, '/') : '';
        $year = gmdate('Y');
        $foot = match($lang) {
            'ar' => "هذه رسالة آليّة من {$site} — لا تردّ عليها.",
            'fr' => "Message automatique de {$site} — merci de ne pas répondre.",
            default => "Automated message from {$site} — please do not reply.",
        };
        $tagline = match($lang) {
            'ar' => 'كندا · المكسيك · أمريكا',
            'fr' => 'Canada · Mexique · États-Unis',
            default => 'Canada · Mexico · USA',
        };

        return ''
          . '<!doctype html><html lang="' . e($lang) . '" dir="' . $dir . '"><head>'
          . '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
          . '</head><body style="margin:0;padding:0;background:#060f1c;font-family:Cairo,Tahoma,Arial,sans-serif;color:#eef4ff">'
          . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#060f1c;padding:24px 12px">'
          . '<tr><td align="center">'
          . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" '
          . 'style="max-width:600px;width:100%;background:linear-gradient(160deg,#0e1d34 0%,#0a1626 100%);'
          . 'border:1px solid rgba(255,255,255,.08);border-radius:18px;overflow:hidden">'
          // Top white bar
          . '<tr><td style="height:6px;background:#fff;font-size:0;line-height:0">&nbsp;</td></tr>'
          // Header with 26 badge
          . '<tr><td align="' . ($ar ? 'right' : 'left') . '" style="padding:22px 28px 8px">'
          . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
          . '<td style="background:#fff;color:#0a1626;font-weight:900;font-size:20px;width:42px;height:42px;'
          . 'border-radius:10px;text-align:center;line-height:42px">26</td>'
          . '<td style="padding:0 12px;color:#9fb3d1;font-size:13px;font-weight:700">'
          . '<div style="color:#eef4ff;font-size:15px">' . e($site) . '</div>'
          . e($tagline) . '</td>'
          . '</tr></table></td></tr>'
          // Inner content
          . '<tr><td style="padding:6px 28px 28px;font-size:15px;line-height:1.8;color:#eef4ff">'
          . $inner
          . '</td></tr>'
          // Footer
          . '<tr><td style="padding:18px 28px;background:rgba(0,0,0,.18);border-top:1px solid rgba(255,255,255,.06);'
          . 'font-size:12px;color:#9fb3d1;line-height:1.7">'
          . e($foot)
          . ($url !== '' ? '<br><a href="' . e($url) . '" style="color:#ffc233;text-decoration:none">' . e($url) . '</a>' : '')
          . ' · &copy; ' . $year
          . '</td></tr>'
          // Bottom accent bar
          . '<tr><td style="height:6px;background:#00d563;font-size:0;line-height:0">&nbsp;</td></tr>'
          . '</table></td></tr></table></body></html>';
    }

    /** يحوّل رابطاً إلى زرّ HTML واضح. */
    private static function button(string $href, string $label): string
    {
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0">'
             . '<tr><td style="background:#fff;border-radius:10px;padding:0">'
             . '<a href="' . e($href) . '" '
             . 'style="display:inline-block;padding:14px 28px;color:#0a1626;font-weight:800;'
             . 'font-size:15px;text-decoration:none;border-radius:10px">'
             . e($label) . '</a></td></tr></table>';
    }

    // ====================================================
    //  1) إيميل الترحيب — يُرسَل بعد التسجيل
    // ====================================================
    public static function welcome(int $userId, string $lang = 'ar'): bool
    {
        $pdo = Database::pdo();
        if (!$pdo instanceof PDO) return false;
        try {
            $st = $pdo->prepare('SELECT email, display_name FROM users WHERE id = ? LIMIT 1');
            $st->execute([$userId]);
            $u = $st->fetch();
        } catch (Throwable $e) { return false; }
        if (!$u || empty($u['email'])) return false;

        $ar    = ($lang === 'ar');
        $fr    = ($lang === 'fr');
        $name  = trim((string)($u['display_name'] ?? '')) ?: match($lang) { 'ar' => 'صديقي', 'fr' => 'ami', default => 'friend' };
        $site  = match($lang) { 'ar' => 'كأس العالم 2026', 'fr' => 'Coupe du Monde 2026', default => 'World Cup 2026' };
        $url   = defined('SITE_URL') ? rtrim((string)SITE_URL, '/') : '';
        $predict = $url . '/predict.php?lang=' . $lang;
        $bracket = $url . '/bracket.php?lang=' . $lang;
        $today   = $url . '/today.php?lang=' . $lang;

        if ($ar) {
            $subj = "أهلاً بك في {$site} 🎉";
            $inner = '<h1 style="font-size:24px;margin:6px 0 12px;color:#fff">أهلاً ' . e($name) . '! 🎉</h1>'
                . '<p style="margin:0 0 10px">سعداء بانضمامك إلى مجتمع <strong>' . e($site) . '</strong> — وجهتك المجانية الكاملة لمتابعة مونديال كندا والمكسيك وأمريكا.</p>'
                . '<p style="margin:12px 0 6px;font-weight:700;color:#ffc233">ما يمكنك فعله الآن:</p>'
                . '<ul style="margin:0;padding-inline-start:22px;color:#cfe0f7">'
                . '<li>🎯 <a href="' . e($predict) . '" style="color:#fff">توقّع المباريات</a> واجمع نقاطاً مع انطلاق البطولة.</li>'
                . '<li>🏆 <a href="' . e($bracket) . '" style="color:#fff">املأ شجرة الأدوار</a> وتوّج بطلك المفضّل.</li>'
                . '<li>📅 <a href="' . e($today) . '" style="color:#fff">شاهد مباريات اليوم</a> بتوقيتك المحلّي.</li>'
                . '</ul>'
                . self::button($predict, 'ابدأ التوقعات ←')
                . '<p style="margin:14px 0 0;font-size:13px;color:#9fb3d1">سؤال؟ راسلنا على '
                . '<a href="mailto:' . e(defined('CONTACT_EMAIL') ? CONTACT_EMAIL : '') . '" style="color:#ffc233">'
                . e(defined('CONTACT_EMAIL') ? CONTACT_EMAIL : '') . '</a></p>';
            $text = "أهلاً {$name}!\n\nسعداء بانضمامك إلى {$site}.\n\nابدأ التوقعات: {$predict}\nاملأ شجرة الأدوار: {$bracket}\nمباريات اليوم: {$today}\n";
        } elseif ($fr) {
            $subj = "Bienvenue sur {$site} 🎉";
            $inner = '<h1 style="font-size:24px;margin:6px 0 12px;color:#fff">Bienvenue, ' . e($name) . ' ! 🎉</h1>'
                . '<p style="margin:0 0 10px">Ravi de vous accueillir dans la communauté <strong>' . e($site) . '</strong> — votre destination gratuite pour suivre la Coupe du Monde au Canada, Mexique et États-Unis.</p>'
                . '<p style="margin:12px 0 6px;font-weight:700;color:#ffc233">Pour commencer :</p>'
                . '<ul style="margin:0;padding-inline-start:22px;color:#cfe0f7">'
                . '<li>🎯 <a href="' . e($predict) . '" style="color:#fff">Prédisez les matchs</a> et gagnez des points tout au long du tournoi.</li>'
                . '<li>🏆 <a href="' . e($bracket) . '" style="color:#fff">Remplissez le tableau</a> et couronnez votre champion.</li>'
                . '<li>📅 <a href="' . e($today) . '" style="color:#fff">Matchs du jour</a> dans votre fuseau horaire.</li>'
                . '</ul>'
                . self::button($predict, 'Commencer les pronostics →')
                . '<p style="margin:14px 0 0;font-size:13px;color:#9fb3d1">Questions ? Écrivez-nous à '
                . '<a href="mailto:' . e(defined('CONTACT_EMAIL') ? CONTACT_EMAIL : '') . '" style="color:#ffc233">'
                . e(defined('CONTACT_EMAIL') ? CONTACT_EMAIL : '') . '</a></p>';
            $text = "Bienvenue, {$name} !\n\nRavi de vous accueillir sur {$site}.\n\nCommencer les pronostics : {$predict}\nRemplir le tableau : {$bracket}\nMatchs du jour : {$today}\n";
        } else {
            $subj = "Welcome to {$site} 🎉";
            $inner = '<h1 style="font-size:24px;margin:6px 0 12px;color:#fff">Welcome, ' . e($name) . '! 🎉</h1>'
                . '<p style="margin:0 0 10px">Glad to have you in the <strong>' . e($site) . '</strong> community — your free, complete home for the Canada · Mexico · USA World Cup.</p>'
                . '<p style="margin:12px 0 6px;font-weight:700;color:#ffc233">Get started:</p>'
                . '<ul style="margin:0;padding-inline-start:22px;color:#cfe0f7">'
                . '<li>🎯 <a href="' . e($predict) . '" style="color:#fff">Predict matches</a> and earn points all tournament long.</li>'
                . '<li>🏆 <a href="' . e($bracket) . '" style="color:#fff">Fill the bracket</a> and crown your champion.</li>'
                . '<li>📅 <a href="' . e($today) . '" style="color:#fff">Today\'s matches</a> in your local time.</li>'
                . '</ul>'
                . self::button($predict, 'Start predicting →')
                . '<p style="margin:14px 0 0;font-size:13px;color:#9fb3d1">Questions? Email us at '
                . '<a href="mailto:' . e(defined('CONTACT_EMAIL') ? CONTACT_EMAIL : '') . '" style="color:#ffc233">'
                . e(defined('CONTACT_EMAIL') ? CONTACT_EMAIL : '') . '</a></p>';
            $text = "Welcome, {$name}!\n\nGlad to have you in {$site}.\n\nStart predicting: {$predict}\nFill the bracket: {$bracket}\nToday's matches: {$today}\n";
        }

        return (bool)@Mailer::send((string)$u['email'], $subj, self::shell($inner, $lang), $text);
    }

    // ====================================================
    //  2) إيميل استعادة كلمة السر
    // ====================================================
    public static function passwordReset(int $userId, string $token, string $lang = 'ar'): bool
    {
        $pdo = Database::pdo();
        if (!$pdo instanceof PDO) return false;
        try {
            $st = $pdo->prepare('SELECT email, display_name FROM users WHERE id = ? LIMIT 1');
            $st->execute([$userId]);
            $u = $st->fetch();
        } catch (Throwable $e) { return false; }
        if (!$u || empty($u['email'])) return false;

        $ar   = ($lang === 'ar');
        $fr   = ($lang === 'fr');
        $name = trim((string)($u['display_name'] ?? '')) ?: match($lang) { 'ar' => 'صديقي', 'fr' => 'ami', default => 'friend' };
        $site = match($lang) { 'ar' => 'كأس العالم 2026', 'fr' => 'Coupe du Monde 2026', default => 'World Cup 2026' };
        $url  = defined('SITE_URL') ? rtrim((string)SITE_URL, '/') : '';
        $link = $url . '/reset.php?token=' . urlencode($token) . '&lang=' . $lang;
        $ip   = self::ipFromServer();

        if ($ar) {
            $subj = "استعادة كلمة السر — {$site}";
            $inner = '<h1 style="font-size:22px;margin:6px 0 12px;color:#fff">طلب استعادة كلمة السر</h1>'
                . '<p style="margin:0 0 10px">أهلاً ' . e($name) . '،</p>'
                . '<p style="margin:0 0 10px">تلقّينا طلباً لاستعادة كلمة سر حسابك في <strong>' . e($site) . '</strong>. اضغط الزر أدناه لتعيين كلمة سر جديدة:</p>'
                . self::button($link, 'تعيين كلمة سر جديدة')
                . '<p style="margin:14px 0 6px;font-size:13px;color:#9fb3d1">صالح لمدّة <strong>ساعة واحدة</strong> فقط، ومرّة واحدة.</p>'
                . '<p style="margin:6px 0;font-size:13px;color:#9fb3d1">إن لم يعمل الزر، انسخ الرابط هذا في متصفّحك:</p>'
                . '<p style="margin:6px 0;font-size:12px;word-break:break-all"><a href="' . e($link) . '" style="color:#ffc233">' . e($link) . '</a></p>'
                . '<p style="margin:18px 0 0;padding:12px 14px;background:rgba(255,194,51,.08);border-inline-start:3px solid #ffc233;font-size:13px;color:#cfe0f7">'
                . '⚠️ <strong>لم تطلب هذا؟</strong> تجاهل هذه الرسالة — كلمة سرّك لن تتغيّر. الطلب جاء من IP: ' . e($ip) . '.</p>';
            $text = "استعادة كلمة السر — {$site}\n\nأهلاً {$name}،\n\nلتعيين كلمة سر جديدة، افتح هذا الرابط (صالح لساعة واحدة):\n{$link}\n\nإن لم تطلب هذا، تجاهل الرسالة. الطلب جاء من IP: {$ip}";
        } elseif ($fr) {
            $subj = "Réinitialisation du mot de passe — {$site}";
            $inner = '<h1 style="font-size:22px;margin:6px 0 12px;color:#fff">Demande de réinitialisation</h1>'
                . '<p style="margin:0 0 10px">Bonjour ' . e($name) . ',</p>'
                . '<p style="margin:0 0 10px">Nous avons reçu une demande de réinitialisation du mot de passe de votre compte sur <strong>' . e($site) . '</strong>. Cliquez sur le bouton ci-dessous pour définir un nouveau mot de passe :</p>'
                . self::button($link, 'Définir un nouveau mot de passe')
                . '<p style="margin:14px 0 6px;font-size:13px;color:#9fb3d1">Valable <strong>1 heure</strong>, usage unique.</p>'
                . '<p style="margin:6px 0;font-size:13px;color:#9fb3d1">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :</p>'
                . '<p style="margin:6px 0;font-size:12px;word-break:break-all"><a href="' . e($link) . '" style="color:#ffc233">' . e($link) . '</a></p>'
                . '<p style="margin:18px 0 0;padding:12px 14px;background:rgba(255,194,51,.08);border-inline-start:3px solid #ffc233;font-size:13px;color:#cfe0f7">'
                . '⚠️ <strong>Vous n\'avez pas fait cette demande ?</strong> Ignorez cet e-mail — votre mot de passe ne changera pas. Demande depuis IP : ' . e($ip) . '.</p>';
            $text = "Réinitialisation du mot de passe — {$site}\n\nBonjour {$name},\n\nPour définir un nouveau mot de passe, ouvrez ce lien (valable 1 heure) :\n{$link}\n\nSi vous n'avez pas fait cette demande, ignorez ce message. Demande depuis IP : {$ip}";
        } else {
            $subj = "Password Reset — {$site}";
            $inner = '<h1 style="font-size:22px;margin:6px 0 12px;color:#fff">Password reset request</h1>'
                . '<p style="margin:0 0 10px">Hi ' . e($name) . ',</p>'
                . '<p style="margin:0 0 10px">We received a request to reset the password for your account on <strong>' . e($site) . '</strong>. Click the button below to set a new password:</p>'
                . self::button($link, 'Set new password')
                . '<p style="margin:14px 0 6px;font-size:13px;color:#9fb3d1">Valid for <strong>1 hour</strong>, single-use.</p>'
                . '<p style="margin:6px 0;font-size:13px;color:#9fb3d1">If the button doesn\'t work, copy this link into your browser:</p>'
                . '<p style="margin:6px 0;font-size:12px;word-break:break-all"><a href="' . e($link) . '" style="color:#ffc233">' . e($link) . '</a></p>'
                . '<p style="margin:18px 0 0;padding:12px 14px;background:rgba(255,194,51,.08);border-inline-start:3px solid #ffc233;font-size:13px;color:#cfe0f7">'
                . '⚠️ <strong>Didn\'t request this?</strong> Ignore this email — your password will not change. Request came from IP: ' . e($ip) . '.</p>';
            $text = "Password Reset — {$site}\n\nHi {$name},\n\nTo set a new password, open this link (valid for 1 hour):\n{$link}\n\nIf you didn't request this, ignore. Request from IP: {$ip}";
        }

        return (bool)@Mailer::send((string)$u['email'], $subj, self::shell($inner, $lang), $text);
    }

    private static function ipFromServer(): string
    {
        $fwd = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($fwd !== '') {
            $first = trim(explode(',', $fwd)[0]);
            if ($first !== '') return $first;
        }
        return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
