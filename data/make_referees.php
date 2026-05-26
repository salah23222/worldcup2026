<?php
/**
 * make_referees.php — يولّد data/referees.json من قائمة FIFA الرسمية.
 * المصدر: Final List of Match Officials FWC 2026 (52 حكم + 88 مساعد + 30 فيديو).
 * شغّله مرة: php data/make_referees.php
 */

// خريطة رمز FIFA الثلاثي → [iso2 للعلَم, اسم عربي, اسم إنجليزي]
$C = [
    'QAT'=>['qa','قطر','Qatar'], 'KSA'=>['sa','السعودية','Saudi Arabia'], 'JPN'=>['jp','اليابان','Japan'],
    'SOM'=>['so','الصومال','Somalia'], 'GAB'=>['ga','الغابون','Gabon'], 'SLV'=>['sv','السلفادور','El Salvador'],
    'MTN'=>['mr','موريتانيا','Mauritania'], 'PAR'=>['py','باراغواي','Paraguay'], 'CRC'=>['cr','كوستاريكا','Costa Rica'],
    'BRA'=>['br','البرازيل','Brazil'], 'USA'=>['us','الولايات المتحدة','USA'], 'NOR'=>['no','النرويج','Norway'],
    'AUS'=>['au','أستراليا','Australia'], 'ARG'=>['ar','الأرجنتين','Argentina'], 'CAN'=>['ca','كندا','Canada'],
    'CHI'=>['cl','تشيلي','Chile'], 'MEX'=>['mx','المكسيك','Mexico'], 'ALG'=>['dz','الجزائر','Algeria'],
    'ESP'=>['es','إسبانيا','Spain'], 'MAR'=>['ma','المغرب','Morocco'], 'NZL'=>['nz','نيوزيلندا','New Zealand'],
    'ROU'=>['ro','رومانيا','Romania'], 'FRA'=>['fr','فرنسا','France'], 'CHN'=>['cn','الصين','China'],
    'JOR'=>['jo','الأردن','Jordan'], 'NED'=>['nl','هولندا','Netherlands'], 'POL'=>['pl','بولندا','Poland'],
    'ITA'=>['it','إيطاليا','Italy'], 'HON'=>['hn','هندوراس','Honduras'], 'EGY'=>['eg','مصر','Egypt'],
    'JAM'=>['jm','جامايكا','Jamaica'], 'SWE'=>['se','السويد','Sweden'], 'ENG'=>['gb-eng','إنجلترا','England'],
    'UAE'=>['ae','الإمارات','UAE'], 'PER'=>['pe','بيرو','Peru'], 'POR'=>['pt','البرتغال','Portugal'],
    'COL'=>['co','كولومبيا','Colombia'], 'SUI'=>['ch','سويسرا','Switzerland'], 'UZB'=>['uz','أوزبكستان','Uzbekistan'],
    'URU'=>['uy','الأوروغواي','Uruguay'], 'VEN'=>['ve','فنزويلا','Venezuela'], 'SVN'=>['si','سلوفينيا','Slovenia'],
    'GER'=>['de','ألمانيا','Germany'], 'RSA'=>['za','جنوب أفريقيا','South Africa'], 'CRO'=>['hr','كرواتيا','Croatia'],
    'NCA'=>['ni','نيكاراغوا','Nicaragua'], 'CMR'=>['cm','الكاميرون','Cameroon'], 'ANG'=>['ao','أنغولا','Angola'],
    'TRI'=>['tt','ترينيداد وتوباغو','Trinidad and Tobago'], 'BEL'=>['be','بلجيكا','Belgium'],
];

$referees = [
    'AL JASSIM Abdulrahman|QAT','AL TURAIS Khalid|KSA','ARAKI Yusuke|JPN','ARTAN Omar Abdulkadir|SOM',
    'ATCHO Pierre|GAB','BARTON Ivan|SLV','BEIDA Dahane|MTN','BENITEZ Juan Gabriel|PAR','CALDERON Juan|CRC',
    'CLAUS Raphael|BRA','ELFATH Ismail|USA','ESKAS Espen|NOR','FAGHANI Alireza|AUS','FALCON PEREZ Yael|ARG',
    'FISCHER Drew|CAN','GARAY Cristian|CHI','GARCIA Katia|MEX','GHORBAL Mustapha|ALG','HERNANDEZ Alejandro|ESP',
    'HERRERA Dario|ARG','JAYED Jalal|MAR','KAWANA-WAUGH Campbell-Kirk|NZL','KOVACS Istvan|ROU','LETEXIER Francois|FRA',
    'MA Ning|CHN','MAKHADMEH Adham|JOR','MAKKELIE Danny|NED','MARCINIAK Szymon|POL','MARIANI Maurizio|ITA',
    'MARTINEZ Hector Said|HON','MOHAMED Amin|EGY','NATION Oshane|JAM','NYBERG Glenn|SWE','OLIVER Michael|ENG',
    'OMAR AL ALI|UAE','ORTEGA Kevin|PER','PENSO Tori|USA','PINHEIRO Joao|POR','RAMON ABATTI|BRA','RAMOS Cesar|MEX',
    'ROJAS Andres|COL','SCHAERER Sandro|SUI','TANTASHEV Ilgiz|UZB','TAYLOR Anthony|ENG','TEJERA Gustavo|URU',
    'TELLO Facundo|ARG','TOM Abongile|RSA','TURPIN Clement|FRA','VALENZUELA Jesus|VEN','VINCIC Slavko|SVN',
    'WILTON SAMPAIO|BRA','ZWAYER Felix|GER',
];

$assistants = [
    'ABEIGNE Amos|GAB','ABOUELREGAL Mahmoud|EGY','AKARKAD Mostafa|MAR','AL ABAKRY Mohammed|KSA','AL HAMMADI Mohamed|UAE',
    'AL KALAF Mohammad|JOR','AL MAQALEH Saoud|QAT','AL MARRI Taleb|QAT','AL ROALLE Ahmad|JOR','ARFA Lyes|CAN',
    'ATKINS Kyle|USA','BARREIRO Carlos|URU','BARWEGEN Micheal|CAN','BASHEVKIN Isaak|NOR','KUPSIK Adam|POL',
    'BEIGI Mahbod|SWE','BELATTI Juan Pablo|ARG','BESWICK Gary|ENG','BINDONI Daniele|ITA','BISGUERRA Marco|MEX',
    'BRINSI Zakaria|MAR','BRUNO BOSCHILIA|BRA','BRUNO PIRES|BRA','BURT Stuart|ENG','CARDOZO Eduardo|PAR',
    'CHADE Gabriel|ARG','DANILO MANIS|BRA','DANOS Nicolas|FRA','DE ALMEIDA Stephane|SUI','DE VRIES Jan|NED',
    'DEL YESSO Maximiliano|ARG','DIETZ Christian|GER','DITSOGA Boris|GAB','ENGAN Jan Erik|NOR','FIGUEIREDO Rodrigo|BRA',
    'GAYNULLIN Timur|UZB','GOURARI Mokrane|ALG','GUZMAN Alexander|COL','HOSSAM TAHA Ahmed|EGY','JERSON SANTOS|ANG',
    'JESUS Bruno|POR','KEMPTER Robert|GER','KLANCNIK Tomaz|SVN','KOVACIC Andraz|SVN','LAKRINDIS George|AUS',
    'LINDSAY James|AUS','LISTKIEWICZ Tomasz|POL','LOPEZ Walter|HON','MAIA Luciano|POR','MAINWARING James|ENG',
    'MARICA Mihai|ROU','MAYO Brooke|USA','MIHARA Jun|JPN','MORA Juan Carlos|CRC','MORAN David|SLV',
    'MORENO Tulio|VEN','MORIN Alberto|MEX','MUGNIER Cyril|FRA','NARANJO PEREZ Jose Enrique|ESP','NAVARRO Cristian|ARG',
    'NESBITT Kathryn|USA','NOUPUE Elvis|CMR','NUNN Adam|ENG','ORUE Michael|PER','PAGES Benjamin|FRA',
    'PARKER Corey|USA','PUPIRO Antonio|NCA','RAFAEL ALVES|BRA','RAHMOUNI Mehdi|FRA','RAMIREZ Christian|HON',
    'RAMIREZ Sandra|MEX','RETAMAL Jose|CHI','ROCHA Miguel|CHI','RODRIGUEZ Facundo|ARG','SALDIVAR Milciades|PAR',
    'SANCHEZ Diego|ESP','SIWELA Zakhele|RSA','SODERKVIST Andreas|SWE','STEEGSTRA Hessel|NED','TARAN Nicolas|URU',
    'TEGONI Alberto|ITA','TREVIS Isaac|NZL','TSAPENKO Andrey|UZB','TUNYOGI Ferencz|ROU','URREGO Jorge|VEN',
    'WALES Caleb|TRI','ZERHOUNI Abbes Akram|ALG','ZHOU Fei|CHN',
];

$var = [
    'AL-MARRI Khamis|QAT','ALSHEHRI Abdullah|KSA','ASHOUR Mahmoud|EGY','BEBEK Ivan|CRO','BRISARD Jerome|FRA',
    'DANKERT Bastian|GER','DEL CERRO GRANDE Carlos|ESP','DI BELLO Marco|ITA','DICKERSON Joe|USA','DIEPERINK Rob|NED',
    'EL FARIQ Hamza|MAR','EVANS Shaun|AUS','FU Ming|CHN','GALLO Nicolas|COL','GARCIA Antonio|URU',
    'GILLETT Jarred|ENG','GONZALEZ Leodan|URU','GUZMAN Tatiana|NCA','HIGLER Dennis|NED','KWIATKOWSKI Tomasz|POL',
    'LARA Juan|CHI','MASTRANGELO Hernan|ARG','MIRANDA Erick|MEX','MOHAMMED OBAID KHADIM|UAE','PACHECO Guillermo|MEX',
    'SAN Fedayi|SUI','SOTO Juan|VEN','TOSKI Rodolpho|BRA','VAN DRIESSCHE Bram|BEL','VILLARREAL Armando|USA',
];

$out  = [];
$miss = [];
$add  = function (array $list, string $role) use (&$out, &$miss, $C) {
    foreach ($list as $row) {
        [$name, $code] = explode('|', $row);
        if (!isset($C[$code])) { $miss[] = $code; continue; }
        [$flag, $ar, $en] = $C[$code];
        $out[] = [
            'name'       => $name,
            'country_ar' => $ar,
            'country_en' => $en,
            'flag'       => $flag,
            'role'       => $role,
        ];
    }
};

$add($referees,  'referee');
$add($assistants,'assistant');
$add($var,       'var');

if ($miss) { fwrite(STDERR, "رموز دول غير معرّفة: " . implode(',', array_unique($miss)) . "\n"); }

file_put_contents(
    __DIR__ . '/referees.json',
    json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);

echo "تم: " . count($out) . " حكماً (حكّام: " . count($referees)
   . " · مساعدون: " . count($assistants) . " · فيديو: " . count($var) . ")\n";
