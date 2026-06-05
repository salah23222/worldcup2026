<?php
/**
 * Stadiums.php — بيانات الملاعب الـ16 + صور ويكيبيديا + مطابقة المدينة.
 *
 * البيانات الأساسية ثابتة (اسم/مدينة/سعة/إحداثيات/سنة الافتتاح/نبذة) فمصدرها داخلي.
 * الصورة تُجلب من ملخّص ويكيبيديا (REST) وتُخزَّن في الكاش — best-effort مع بديل آمن.
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Stadiums
{
    /** @var array<int,array>|null */
    private static $cache = null;

    /** كل الملاعب مع المفتاح id = ترتيبها. */
    public static function all(): array
    {
        if (self::$cache !== null) return self::$cache;

        $rows = [
            // nameAr, nameEn, cityAr, cityEn, country, cap, lat, lng, opened, wiki, histAr, histEn, nameFr, cityFr, histFr
            ['ملعب ميتلايف','MetLife Stadium','نيويورك/نيوجيرسي','New York/New Jersey','us',82500,40.8135,-74.0744,2010,'MetLife Stadium',
                'افتُتح عام 2010 في إيست رذرفورد بولاية نيوجيرسي، وهو ملعب فريقي نيويورك جاينتس وجِتس للرياضات الأمريكية. اختير لاستضافة المباراة النهائية لكأس العالم 2026.',
                'Opened in 2010 in East Rutherford, New Jersey, it is home to the NFL\'s Giants and Jets. It will host the 2026 World Cup Final.',
                'MetLife Stadium','New York/New Jersey',
                'Inauguré en 2010 à East Rutherford, dans le New Jersey, il est le stade des Giants et des Jets de la NFL. Il accueillera la finale de la Coupe du Monde 2026.'],
            ['ملعب دالاس','AT&T Stadium','دالاس','Dallas','us',80000,32.7473,-97.0945,2009,'AT&T Stadium',
                'ملعب فريق دالاس كاوبويز، افتُتح عام 2009 ويتميّز بسقف متحرّك وأكبر شاشة عرض معلّقة. يُعرف باسم «ملعب دالاس» خلال البطولة.',
                'Home of the Dallas Cowboys, opened in 2009 with a retractable roof and a giant center-hung screen. It is branded "Dallas Stadium" for the tournament.',
                'AT&T Stadium','Dallas',
                'Stade des Dallas Cowboys, inauguré en 2009 avec un toit rétractable et un écran géant suspendu. Il est nommé «Dallas Stadium» pendant le tournoi.'],
            ['ملعب مرسيدس بنز','Mercedes-Benz Stadium','أتلانتا','Atlanta','us',71000,33.7554,-84.4008,2017,'Mercedes-Benz Stadium',
                'افتُتح عام 2017 في أتلانتا ويشتهر بسقفه المتحرّك على شكل بتلات. يستضيف فريقي أتلانتا فالكونز وأتلانتا يونايتد.',
                'Opened in 2017 in Atlanta, famous for its retractable petal-shaped roof. It hosts the Atlanta Falcons and Atlanta United.',
                'Mercedes-Benz Stadium','Atlanta',
                'Inauguré en 2017 à Atlanta, célèbre pour son toit rétractable en forme de pétales. Il accueille les Falcons et Atlanta United.'],
            ['ملعب سوفاي','SoFi Stadium','لوس أنجلوس','Los Angeles','us',70240,33.9535,-118.3392,2020,'SoFi Stadium',
                'افتُتح عام 2020 في إنغلوود بلوس أنجلوس، وهو من أغلى الملاعب في العالم. يستضيف فريقي لوس أنجلوس رامز وتشارجرز.',
                'Opened in 2020 in Inglewood, Los Angeles, among the most expensive stadiums ever built. It hosts the LA Rams and Chargers.',
                'SoFi Stadium','Los Angeles',
                'Inauguré en 2020 à Inglewood, Los Angeles, parmi les stades les plus chers jamais construits. Il accueille les Rams et les Chargers.'],
            ['ملعب لومن فيلد','Lumen Field','سياتل','Seattle','us',68740,47.5952,-122.3316,2002,'Lumen Field',
                'افتُتح عام 2002 في سياتل ويشتهر بجماهيره الصاخبة. يستضيف فريقي سياتل سيهوكس وساوندرز.',
                'Opened in 2002 in Seattle, known for its famously loud crowds. It hosts the Seahawks and Sounders.',
                'Lumen Field','Seattle',
                'Inauguré en 2002 à Seattle, connu pour ses supporters bruyants. Il accueille les Seahawks et les Sounders.'],
            ['ملعب ليفايز','Levi\'s Stadium','سان فرانسيسكو','San Francisco Bay Area','us',68500,37.4030,-121.9700,2014,'Levi\'s Stadium',
                'افتُتح عام 2014 في سانتا كلارا بمنطقة خليج سان فرانسيسكو، وهو ملعب فريق سان فرانسيسكو فورتي ناينرز.',
                'Opened in 2014 in Santa Clara in the San Francisco Bay Area, home of the San Francisco 49ers.',
                'Levi\'s Stadium','San Francisco',
                'Inauguré en 2014 à Santa Clara dans la baie de San Francisco, stade des 49ers.'],
            ['ملعب إن آر جي','NRG Stadium','هيوستن','Houston','us',72220,29.6847,-95.4107,2002,'NRG Stadium',
                'افتُتح عام 2002 في هيوستن وكان أول ملعب في الدوري الأمريكي بسقف متحرّك. يستضيف فريق هيوستن تكسانز.',
                'Opened in 2002 in Houston, it was the NFL\'s first retractable-roof stadium. Home of the Houston Texans.',
                'NRG Stadium','Houston',
                'Inauguré en 2002 à Houston, premier stade de la NFL avec toit rétractable. Stade des Texans.'],
            ['ملعب أروهيد','Arrowhead Stadium','كانساس سيتي','Kansas City','us',76416,39.0489,-94.4839,1972,'Arrowhead Stadium',
                'افتُتح عام 1972 وهو موطن فريق كانساس سيتي تشيفز، وسجّل رقماً قياسياً كأعلى ملعب صوتاً في العالم.',
                'Opened in 1972, home of the Kansas City Chiefs and once recorded as the loudest stadium in the world.',
                'Arrowhead Stadium','Kansas City',
                'Inauguré en 1972, stade des Chiefs, autrefois détenteur du record du stade le plus bruyant du monde.'],
            ['ملعب جيليت','Gillette Stadium','بوسطن','Boston','us',65878,42.0909,-71.2643,2002,'Gillette Stadium',
                'افتُتح عام 2002 في فوكسبورو قرب بوسطن، وهو ملعب فريقي نيو إنغلاند باتريوتس وريفوليوشن.',
                'Opened in 2002 in Foxborough near Boston, home of the New England Patriots and Revolution.',
                'Gillette Stadium','Boston',
                'Inauguré en 2002 à Foxborough près de Boston, stade des Patriots et de la Revolution.'],
            ['ملعب لينكولن فاينانشال','Lincoln Financial Field','فيلادلفيا','Philadelphia','us',69176,39.9008,-75.1675,2003,'Lincoln Financial Field',
                'افتُتح عام 2003 في فيلادلفيا وهو موطن فريق فيلادلفيا إيغلز.',
                'Opened in 2003 in Philadelphia, home of the Philadelphia Eagles.',
                'Lincoln Financial Field','Philadelphia',
                'Inauguré en 2003 à Philadelphia, stade des Eagles.'],
            ['ملعب هارد روك','Hard Rock Stadium','ميامي','Miami','us',65326,25.9580,-80.2389,1987,'Hard Rock Stadium',
                'افتُتح عام 1987 في ميامي غاردنز، وهو ملعب فريق ميامي دولفينز ويستضيف بطولة ميامي المفتوحة وسباق الفورمولا 1.',
                'Opened in 1987 in Miami Gardens, home of the Miami Dolphins and host of the Miami Open and F1.',
                'Hard Rock Stadium','Miami',
                'Inauguré en 1987 à Miami Gardens, stade des Dolphins, accueille le Miami Open et le F1.'],
            ['ملعب أزتيكا','Estadio Azteca','مكسيكو سيتي','Mexico City','mx',83264,19.3029,-99.1505,1966,'Estadio Azteca',
                'افتُتح عام 1966 وهو الملعب الوحيد الذي استضاف نهائيي كأس العالم 1970 و1986. سيستضيف المباراة الافتتاحية لمونديال 2026 ليصبح أول ملعب يشهد ثلاث بطولات عالمية.',
                'Opened in 1966, it is the only stadium to have hosted two World Cup finals (1970 and 1986). It will host the 2026 opening match, becoming the first to feature in three World Cups.',
                'Estadio Azteca','Mexico',
                'Inauguré en 1966, seul stade à avoir accueilli deux finales de Coupe du Monde (1970 et 1986). Il accueillera le match d\'ouverture 2026, devenant le premier à participer à trois Coupes du Monde.'],
            ['ملعب أكرون','Estadio Akron','غوادالاخارا','Guadalajara','mx',46355,20.6819,-103.4626,2010,'Estadio Akron',
                'افتُتح عام 2010 في زاپوپان بغوادالاخارا، وهو ملعب نادي تشيفاس غوادالاخارا.',
                'Opened in 2010 in Zapopan, Guadalajara, home of Chivas Guadalajara.',
                'Estadio Akron','Guadalajara',
                'Inauguré en 2010 à Zapopan, Guadalajara, stade du Chivas.'],
            ['ملعب بي بي في إيه','Estadio BBVA','مونتيري','Monterrey','mx',53500,25.6694,-100.2447,2015,'Estadio BBVA',
                'افتُتح عام 2015 في غوادالوبي بمونتيري، وهو ملعب نادي رايادوس ويتميّز بإطلالته على جبل سييرا مادري.',
                'Opened in 2015 in Guadalupe, Monterrey, home of Rayados, with a striking Sierra Madre mountain backdrop.',
                'Estadio BBVA','Monterrey',
                'Inauguré en 2015 à Guadalupe, Monterrey, stade de Rayados, avec une vue spectaculaire sur la Sierra Madre.'],
            ['ملعب بي إم أو فيلد','BMO Field','تورونتو','Toronto','ca',45000,43.6332,-79.4185,2007,'BMO Field',
                'افتُتح عام 2007 في تورونتو وهو ملعب نادي تورونتو إف سي، ويجري توسيعه استعداداً لكأس العالم.',
                'Opened in 2007 in Toronto, home of Toronto FC, and being expanded for the World Cup.',
                'BMO Field','Toronto',
                'Inauguré en 2007 à Toronto, stade du Toronto FC, en cours d\'extension pour la Coupe du Monde.'],
            ['ملعب بي سي بليس','BC Place','فانكوفر','Vancouver','ca',54500,49.2768,-123.1119,1983,'BC Place',
                'افتُتح عام 1983 في فانكوفر وأعيد تجديده عام 2011 بسقف متحرّك. يستضيف نادي فانكوفر وايتكابس.',
                'Opened in 1983 in Vancouver and renovated in 2011 with a retractable roof. It hosts the Vancouver Whitecaps.',
                'BC Place','Vancouver',
                'Inauguré en 1983 à Vancouver, rénové en 2011 avec un toit rétractable. Stade des Whitecaps.'],
        ];

        $out = [];
        foreach ($rows as $i => $r) {
            $out[$i] = [
                'id'      => $i,
                'nameAr'  => $r[0],  'nameEn' => $r[1],
                'cityAr'  => $r[2],  'cityEn' => $r[3],
                'country' => $r[4],  'cap'    => $r[5],
                'lat'     => $r[6],  'lng'    => $r[7],
                'opened'  => $r[8],  'wiki'   => $r[9],
                'histAr'  => $r[10], 'histEn' => $r[11],
                'nameFr'  => $r[12], 'cityFr' => $r[13], 'histFr' => $r[14],
            ];
        }
        return self::$cache = $out;
    }

    public static function get(int $id): ?array
    {
        $all = self::all();
        return $all[$id] ?? null;
    }

    /**
     * مطابقة قيمة "ground" من بيانات openfootball (اسم مدينة، قد يتبعه بين قوسين).
     * مثال: "Guadalajara (Zapopan)" → ملعب أكرون.
     */
    public static function byGround(string $ground): ?array
    {
        $g = trim($ground);
        if ($g === '') return null;
        $base = trim(preg_replace('/\s*\(.*$/', '', $g));   // أزل ما بين القوسين
        $bl   = mb_strtolower($base);

        foreach (self::all() as $s) {
            if (mb_strtolower($s['cityEn']) === $bl) return $s;
        }
        foreach (self::all() as $s) {
            $c = mb_strtolower($s['cityEn']);
            if ($c !== '' && (mb_strpos($c, $bl) !== false || mb_strpos($bl, $c) !== false)) return $s;
        }
        return null;
    }

    /** رابط خرائط جوجل للتوجيه إلى الملعب. */
    public static function mapsUrl(array $s): string
    {
        return 'https://www.google.com/maps/dir/?api=1&destination='
             . rawurlencode($s['lat'] . ',' . $s['lng']);
    }

    /**
     * صورة الملعب من ملخّص ويكيبيديا، مع كاش 30 يوماً.
     * تُعيد رابط الصورة أو سلسلة فارغة عند الفشل (لا تُخزّن الفشل ليُعاد المحاولة لاحقاً).
     */
    public static function image(int $id): string
    {
        $s = self::get($id);
        if (!$s) return '';

        $dir = __DIR__ . '/../cache/stadiums';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $cf = $dir . '/img_' . $id . '.txt';

        if (is_file($cf) && (time() - filemtime($cf) < 2592000)) {
            return trim((string)@file_get_contents($cf));
        }

        $url  = 'https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode($s['wiki']);
        $body = self::httpGet($url);
        $img  = '';
        if ($body) {
            $d     = json_decode($body, true);
            $thumb = $d['thumbnail']['source'] ?? '';                 // مقاس مضمون (مُخزّن مسبقاً)
            $orig  = $d['originalimage']['source'] ?? '';
            $img   = $thumb !== '' ? $thumb : $orig;

            // حاول رفع الدقّة لنسخة أوضح، لكن أبقِها فقط إذا كانت تُحمَّل فعلاً
            // (ويكيميديا ترفض بعض المقاسات بـ400، فنعود حينها للمقاس المضمون)
            if ($thumb !== '') {
                $big = preg_replace('#/\d+px-#', '/1024px-', $thumb);
                if ($big !== null && $big !== $thumb && self::urlOk($big)) {
                    $img = $big;
                }
            }
        }
        if ($img !== '') {
            @file_put_contents($cf, $img);
        }
        return $img;
    }

    /** فحص سريع: هل يُعيد الرابط 200؟ (يُستخدم لتأكيد توفّر صورة بدقّة أعلى) */
    private static function urlOk(string $url): bool
    {
        if (!function_exists('curl_init')) return false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (WorldCup2026Site)',
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200;
    }

    private static function httpGet(string $url): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_USERAGENT      => 'WorldCup2026Site/1.0 (stadium info)',
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            ]);
            $res = curl_exec($ch);
            $ok  = ($res !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200);
            curl_close($ch);
            return $ok ? (string)$res : '';
        }

        $ctx = stream_context_create(['http' => [
            'timeout' => 6,
            'header'  => "User-Agent: WorldCup2026Site/1.0\r\nAccept: application/json\r\n",
        ]]);
        $res = @file_get_contents($url, false, $ctx);
        return $res !== false ? (string)$res : '';
    }
}
