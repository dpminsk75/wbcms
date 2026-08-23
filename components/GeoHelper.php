<?php

namespace app\components;

class GeoHelper
{
public static function getRegionData()
{
    return [
    // Проблемные регионы, которые мы обсуждали:
    ['iso' => 'RU-KGN', 'hc' => 'ru-ku', 'name' => 'Kurgan', 'ru_name' => 'Курганская область'],
    ['iso' => 'RU-KRS', 'hc' => 'ru-ks', 'name' => 'Kursk', 'ru_name' => 'Курская область'],
    ['iso' => 'RU-KIR', 'hc' => 'ru-kv', 'name' => 'Kirov', 'ru_name' => 'Кировская область'],
    ['iso' => 'RU-KOS', 'hc' => 'ru-kt', 'name' => 'Kostroma', 'ru_name' => 'Костромская область'],
    
    // Новгороды (проверено по файлу):
    ['iso' => 'RU-NIZ', 'hc' => 'ru-nz', 'name' => 'Nizhegorod', 'ru_name' => 'Нижегородская область'], 
    ['iso' => 'RU-NGR', 'hc' => 'ru-ng', 'name' => 'Novgorod', 'ru_name' => 'Новгородская область'],

    // Города федерального значения (в этой версии файла):
    ['iso' => 'RU-MOW', 'hc' => 'ru-2510', 'name' => 'Moscow City', 'ru_name' => 'Москва'],
    ['iso' => 'RU-SPE', 'hc' => 'ru-sp',   'name' => 'St. Petersburg City', 'ru_name' => 'Санкт-Петербург'],
    
    // Остальные (строго по вашему ru-all.js):
    ['iso' => 'RU-AD', 'hc' => 'ru-ad', 'name' => 'Adygey', 'ru_name' => 'Адыгея'],
    ['iso' => 'RU-AL', 'hc' => 'ru-ga', 'name' => 'Gorno-Altay', 'ru_name' => 'Республика Алтай'],
    ['iso' => 'RU-BA', 'hc' => 'ru-bk', 'name' => 'Bashkortostan', 'ru_name' => 'Башкортостан'],
    ['iso' => 'RU-BU', 'hc' => 'ru-bu', 'name' => 'Buryat', 'ru_name' => 'Бурятия'],
    ['iso' => 'RU-DA', 'hc' => 'ru-da', 'name' => 'Dagestan', 'ru_name' => 'Дагестан'],
    ['iso' => 'RU-IN', 'hc' => 'ru-in', 'name' => 'Ingush', 'ru_name' => 'Ингушетия'],
    ['iso' => 'RU-KB', 'hc' => 'ru-kb', 'name' => 'Kabardin-Balkar', 'ru_name' => 'Кабардино-Балкария'],
    ['iso' => 'RU-KL', 'hc' => 'ru-kl', 'name' => 'Kalmyk', 'ru_name' => 'Калмыкия'],
    ['iso' => 'RU-KC', 'hc' => 'ru-kc', 'name' => 'Karachay-Cherkess', 'ru_name' => 'Карачаево-Черкесия'],
    ['iso' => 'RU-KR', 'hc' => 'ru-ki', 'name' => 'Karelia', 'ru_name' => 'Карелия'],
    ['iso' => 'RU-KO', 'hc' => 'ru-ko', 'name' => 'Komi', 'ru_name' => 'Коми'],
    ['iso' => 'RU-ME', 'hc' => 'ru-me', 'name' => 'Mari-El', 'ru_name' => 'Марий Эл'],
    ['iso' => 'RU-MO', 'hc' => 'ru-mr', 'name' => 'Mordovia', 'ru_name' => 'Мордовия'],
    ['iso' => 'RU-SA', 'hc' => 'ru-sk', 'name' => 'Sakha', 'ru_name' => 'Якутия'],
    ['iso' => 'RU-SE', 'hc' => 'ru-no', 'name' => 'North Ossetia', 'ru_name' => 'Северная Осетия'],
    ['iso' => 'RU-TA', 'hc' => 'ru-tt', 'name' => 'Tatarstan', 'ru_name' => 'Татарстан'],
    ['iso' => 'RU-TY', 'hc' => 'ru-tu', 'name' => 'Tyva', 'ru_name' => 'Тыва'],
    ['iso' => 'RU-UD', 'hc' => 'ru-ud', 'name' => 'Udmurt', 'ru_name' => 'Удмуртия'],
    ['iso' => 'RU-KK', 'hc' => 'ru-kk', 'name' => 'Khakass', 'ru_name' => 'Хакасия'],
    ['iso' => 'RU-CE', 'hc' => 'ru-cn', 'name' => 'Chechnya', 'ru_name' => 'Чечня'],
    ['iso' => 'RU-CU', 'hc' => 'ru-cv', 'name' => 'Chuvash', 'ru_name' => 'Чувашия'],
    ['iso' => 'RU-ALT', 'hc' => 'ru-al', 'name' => 'Altay', 'ru_name' => 'Алтайский край'],
    ['iso' => 'RU-ZAB', 'hc' => 'ru-ct', 'name' => 'Zabaykal\'ye Chita', 'ru_name' => 'Забайкальский край'],
    ['iso' => 'RU-KAM', 'hc' => 'ru-ka', 'name' => 'Kamchatka', 'ru_name' => 'Камчатский край'],
    ['iso' => 'RU-KDA', 'hc' => 'ru-kd', 'name' => 'Krasnodar', 'ru_name' => 'Краснодарский край'],
    ['iso' => 'RU-KYA', 'hc' => 'ru-ky', 'name' => 'Krasnoyarsk', 'ru_name' => 'Красноярский край'],
    ['iso' => 'RU-PER', 'hc' => 'ru-pe', 'name' => 'Perm\'', 'ru_name' => 'Пермский край'],
    ['iso' => 'RU-PRI', 'hc' => 'ru-pr', 'name' => 'Primor\'ye', 'ru_name' => 'Приморский край'],
    ['iso' => 'RU-STA', 'hc' => 'ru-st', 'name' => 'Stavropol\'', 'ru_name' => 'Ставропольский край'],
    ['iso' => 'RU-KHA', 'hc' => 'ru-kh', 'name' => 'Khabarovsk', 'ru_name' => 'Хабаровский край'],
    ['iso' => 'RU-AMU', 'hc' => 'ru-am', 'name' => 'Amur', 'ru_name' => 'Амурская область'],
    ['iso' => 'RU-ARK', 'hc' => 'ru-ar', 'name' => 'Arkhangel\'sk', 'ru_name' => 'Архангельская область'],
    ['iso' => 'RU-AST', 'hc' => 'ru-as', 'name' => 'Astrakhan\'', 'ru_name' => 'Астраханская область'],
    ['iso' => 'RU-BEL', 'hc' => 'ru-bl', 'name' => 'Belgorod', 'ru_name' => 'Белгородская область'],
    ['iso' => 'RU-BRY', 'hc' => 'ru-br', 'name' => 'Bryansk', 'ru_name' => 'Брянская область'],
    ['iso' => 'RU-VLA', 'hc' => 'ru-vl', 'name' => 'Vladimir', 'ru_name' => 'Владимирская область'],
    ['iso' => 'RU-VGG', 'hc' => 'ru-vg', 'name' => 'Volgograd', 'ru_name' => 'Волгоградская область'],
    ['iso' => 'RU-VLG', 'hc' => 'ru-vo', 'name' => 'Vologda', 'ru_name' => 'Вологодская область'],
    ['iso' => 'RU-VOR', 'hc' => 'ru-vr', 'name' => 'Voronezh', 'ru_name' => 'Воронежская область'],
    ['iso' => 'RU-IVA', 'hc' => 'ru-iv', 'name' => 'Ivanovo', 'ru_name' => 'Ивановская область'],
    ['iso' => 'RU-IRK', 'hc' => 'ru-ir', 'name' => 'Irkutsk', 'ru_name' => 'Иркутская область'],
    ['iso' => 'RU-KGD', 'hc' => 'ru-kn', 'name' => 'Kaliningrad', 'ru_name' => 'Калининградская область'],
    ['iso' => 'RU-KLU', 'hc' => 'ru-kg', 'name' => 'Kaluga', 'ru_name' => 'Калужская область'],
    ['iso' => 'RU-KEM', 'hc' => 'ru-ke', 'name' => 'Kemerovo', 'ru_name' => 'Кемеровская область'],
    ['iso' => 'RU-LEN', 'hc' => 'ru-ln', 'name' => 'Leningrad', 'ru_name' => 'Ленинградская область'],
    ['iso' => 'RU-LIP', 'hc' => 'ru-lp', 'name' => 'Lipetsk', 'ru_name' => 'Липецкая область'],
    ['iso' => 'RU-MAG', 'hc' => 'ru-mg', 'name' => 'Magadan', 'ru_name' => 'Магаданская область'],
    ['iso' => 'RU-MOS', 'hc' => 'ru-ms', 'name' => 'Moskva', 'ru_name' => 'Московская область'],
    ['iso' => 'RU-MUR', 'hc' => 'ru-mm', 'name' => 'Murmansk', 'ru_name' => 'Мурманская область'],
    ['iso' => 'RU-NVS', 'hc' => 'ru-ns', 'name' => 'Novosibirsk', 'ru_name' => 'Новосибирская область'],
    ['iso' => 'RU-OMS', 'hc' => 'ru-om', 'name' => 'Omsk', 'ru_name' => 'Омская область'],
    ['iso' => 'RU-ORE', 'hc' => 'ru-ob', 'name' => 'Orenburg', 'ru_name' => 'Оренбургская область'],
    ['iso' => 'RU-ORL', 'hc' => 'ru-ol', 'name' => 'Orel', 'ru_name' => 'Орловская область'],
    ['iso' => 'RU-PNZ', 'hc' => 'ru-pz', 'name' => 'Penza', 'ru_name' => 'Пензенская область'],
    ['iso' => 'RU-PSK', 'hc' => 'ru-ps', 'name' => 'Pskov', 'ru_name' => 'Псковская область'],
    ['iso' => 'RU-ROS', 'hc' => 'ru-ro', 'name' => 'Rostov', 'ru_name' => 'Ростовская область'],
    ['iso' => 'RU-RYA', 'hc' => 'ru-rz', 'name' => 'Ryazan\'', 'ru_name' => 'Рязанская область'],
    ['iso' => 'RU-SAM', 'hc' => 'ru-sa', 'name' => 'Samara', 'ru_name' => 'Самарская область'],
    ['iso' => 'RU-SAR', 'hc' => 'ru-sr', 'name' => 'Saratov', 'ru_name' => 'Саратовская область'],
    ['iso' => 'RU-SAK', 'hc' => 'ru-sl', 'name' => 'Sakhalin', 'ru_name' => 'Сахалинская область'],
    ['iso' => 'RU-SVE', 'hc' => 'ru-sv', 'name' => 'Sverdlovsk', 'ru_name' => 'Свердловская область'],
    ['iso' => 'RU-SMO', 'hc' => 'ru-sm', 'name' => 'Smolensk', 'ru_name' => 'Смоленская область'],
    ['iso' => 'RU-TAM', 'hc' => 'ru-tb', 'name' => 'Tambov', 'ru_name' => 'Тамбовская область'],
    ['iso' => 'RU-TVE', 'hc' => 'ru-tv', 'name' => 'Tver\'', 'ru_name' => 'Тверская область'],
    ['iso' => 'RU-TOM', 'hc' => 'ru-to', 'name' => 'Tomsk', 'ru_name' => 'Томская область'],
    ['iso' => 'RU-TUL', 'hc' => 'ru-tl', 'name' => 'Tula', 'ru_name' => 'Тульская область'],
    ['iso' => 'RU-TYU', 'hc' => 'ru-ty', 'name' => 'Tyumen\'', 'ru_name' => 'Тюменская область'],
    ['iso' => 'RU-ULY', 'hc' => 'ru-ul', 'name' => 'Ulyanovsk', 'ru_name' => 'Ульяновская область'],
    ['iso' => 'RU-CHE', 'hc' => 'ru-cl', 'name' => 'Chelyabinsk', 'ru_name' => 'Челябинская область'],
    ['iso' => 'RU-YAR', 'hc' => 'ru-ys', 'name' => 'Yaroslavl\'', 'ru_name' => 'Ярославская область'],
    ['iso' => 'RU-SEV', 'hc' => 'ru-se', 'name' => 'Sevastopol\'', 'ru_name' => 'Севастополь'],
    ['iso' => 'RU-YEV', 'hc' => 'ru-yv', 'name' => 'Yevrey', 'ru_name' => 'Еврейская АО'],
    ['iso' => 'RU-NEN', 'hc' => 'ru-nn', 'name' => 'Nenets', 'ru_name' => 'Ненецкий АО'],
    ['iso' => 'RU-KHM', 'hc' => 'ru-km', 'name' => 'Khanty-Mansiy', 'ru_name' => 'Ханты-Мансийский АО - Югра'],
    ['iso' => 'RU-CHU', 'hc' => 'ru-ck', 'name' => 'Chukot', 'ru_name' => 'Чукотский АО'],
    ['iso' => 'RU-YAN', 'hc' => 'ru-yn', 'name' => 'Yamal-Nenets', 'ru_name' => 'Ямало-Ненецкий АО'],
    ['iso' => 'RU-KRY', 'hc' => 'ru-kr', 'name' => 'Krym', 'ru_name' => 'Республика Крым'],
    ['iso' => 'UA-43' , 'hc' => 'ru-14',  'name' => 'Krym', 'ru_name' => 'Республика Крым'],
    ['iso' => 'UA-40' , 'hc' => 'ua-sev', 'name' => 'Sevastopol', 'ru_name' => 'Севастополь'], // ua-sev ru-se


    ];
}

public static function prepareChartData($mapData)
{
    $reference = self::getRegionData();
    $lookup = [];
    foreach ($reference as $reg) {
        $lookup[strtolower($reg['iso'])] = $reg;
    }

    $finalChart = [];
    $debugTable = [];

    foreach ($mapData as $row) {
        $dbName = $row['name'] ?? 'Неизвестно';
        $dbKey = isset($row['hc_key']) ? trim($row['hc_key']) : '';
        $lowKey = strtolower($dbKey);
        
        $regionMeta = $lookup[$lowKey] ?? null;
        $hcKey = $regionMeta ? $regionMeta['hc'] : null;

            if ($hcKey) {
                $finalChart[] = [
                    'hc-key' => (string)$hcKey, // Строго строка
                    'value'  => (int)($row['sales_count'] ?? 0), 
                    'name'   => (string)$dbName,
                    'retail_sum' => (float)($row['retail_amount']) 
                ];
            }

        // Ключи должны СТРОГО совпадать с тем, что мы пишем во View
        $debugTable[] = [
            'db_name' => $dbName,
            'db_key' => $dbKey,
            'meta' => $regionMeta, 
            'result_hc' => $hcKey, // <--- Используем это имя
            'count' => $row['sales_count']
        ];
    }

    return ['chart' => $finalChart, 'debug' => $debugTable];
}


}