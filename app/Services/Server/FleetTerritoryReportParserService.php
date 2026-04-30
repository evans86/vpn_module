<?php

namespace App\Services\Server;

/**
 * Разбирает текстовые отчёты скриптов типа Net check (RU-разделы 1–6).
 */
class FleetTerritoryReportParserService
{
    /**
     * Нормализует BOM / UTF-16LE с Windows-сохранений.
     */
    public function normalizeEncoding(string $raw): string
    {
        if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
            $raw = substr($raw, 3);
        }
        $len = strlen($raw);
        if ($len >= 2 && substr($raw, 0, 2) === "\xFF\xFE") {
            $tail = substr($raw, 2);
            $converted = mb_convert_encoding($tail, 'UTF-8', 'UTF-16LE');

            return ($converted !== false && $converted !== '') ? $converted : $tail;
        }
        if ($len >= 2 && substr($raw, 0, 2) === "\xFE\xFF") {
            $tail = substr($raw, 2);
            $converted = mb_convert_encoding($tail, 'UTF-8', 'UTF-16BE');

            return ($converted !== false && $converted !== '') ? $converted : $tail;
        }

        return $raw;
    }

    /**
     * @return array{
     *   mode_label: ?string,
     *   public_ip: ?string,
     *   geo_service: ?string,
     *   country_name: ?string,
     *   country_code: ?string,
     *   region: ?string,
     *   city: ?string,
     *   isp: ?string,
     *   asn: ?string,
     *   geo_parse_error: ?string,
     * }
     */
    public function parse(string $raw): array
    {
        $raw = $this->normalizeEncoding($raw);
        $result = [
            'mode_label' => null,
            'public_ip' => null,
            'geo_service' => null,
            'country_name' => null,
            'country_code' => null,
            'region' => null,
            'city' => null,
            'isp' => null,
            'asn' => null,
            'geo_parse_error' => null,
        ];

        if (preg_match('/^Режим:\s*(.+)$/mu', $raw, $m)) {
            $result['mode_label'] = trim($m[1]);
        }

        $geoBody = null;
        if (preg_match('/^2\.\s*[^\r\n]*[\r\n]+=+[\s\r\n]*([\s\S]+?)(?=^\d+\.\s)/m', $raw, $geoMatch)) {
            $geoBody = $geoMatch[1];
        }

        if ($geoBody !== null) {
            $this->fillFromGeoSection(trim($geoBody), $result);
        }

        // Fallback по всему файлу — если блок «2» не распознан (другая локаль или формат)
        if ($result['geo_parse_error'] === null
            && $result['country_name'] === null
            && $result['country_code'] === null
            && ($result['public_ip'] === null || $geoBody === null)
        ) {
            $this->fallbackFromWholeReport($raw, $result);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function fillFromGeoSection(string $geoBody, array &$result): void
    {
        if (preg_match('/^Пропущено\b/mu', $geoBody)) {
            $result['geo_parse_error'] = 'Секция 2 GeoIP не выполнялась (пропуск в скрипте).';

            return;
        }

        if (preg_match('/^GeoIP\s*ошибка:\s*(.+)$/mu', $geoBody, $em)) {
            $result['geo_parse_error'] = trim($em[1]);

            return;
        }

        if (preg_match('/^Нет данных\.\s*$/mu', $geoBody)) {
            $result['geo_parse_error'] = 'В отчёте нет данных GeoIP (секция 2).';

            return;
        }

        foreach (preg_split("/\r\n|\r|\n/", $geoBody) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $this->matchGeoLine($line, $result);
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function matchGeoLine(string $line, array &$result): void
    {
        if ($result['public_ip'] === null && preg_match('/^IP\s+для\s+GeoIP:\s*(.+)$/u', $line, $mm)) {
            $result['public_ip'] = trim($mm[1]);

            return;
        }
        if ($result['geo_service'] === null && preg_match('/^Сервис:\s*(.+)$/u', $line, $mm)) {
            $result['geo_service'] = trim($mm[1]);

            return;
        }
        if (($result['country_name'] === null || $result['country_code'] === null)
            && preg_match('/^Страна:\s*(.+?)\s*\(([^)]+)\)\s*$/u', $line, $mm)) {
            $result['country_name'] = trim($mm[1]);
            $result['country_code'] = trim($mm[2]);

            return;
        }
        if ($result['region'] === null && preg_match('/^Регион:\s*(.*)$/u', $line, $mm)) {
            $result['region'] = trim($mm[1]) !== '' ? trim($mm[1]) : null;

            return;
        }
        if ($result['city'] === null && preg_match('/^Город:\s*(.*)$/u', $line, $mm)) {
            $result['city'] = trim($mm[1]) !== '' ? trim($mm[1]) : null;

            return;
        }
        if ($result['isp'] === null && preg_match('/^ISP:\s*(.*)$/u', $line, $mm)) {
            $result['isp'] = trim($mm[1]) !== '' ? trim($mm[1]) : null;

            return;
        }
        if ($result['asn'] === null && preg_match('/^ASN:\s*(.+)$/u', $line, $mm)) {
            $result['asn'] = trim($mm[1]) !== '' ? trim($mm[1]) : null;
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function fallbackFromWholeReport(string $raw, array &$result): void
    {
        if ($result['public_ip'] === null && preg_match('/^IP\s+для\s+GeoIP:\s*(.+)$/mu', $raw, $mm)) {
            $result['public_ip'] = trim($mm[1]);
        }
        if ($result['geo_service'] === null && preg_match('/^Сервис:\s*(.+)$/mu', $raw, $mm)) {
            $result['geo_service'] = trim($mm[1]);
        }
        if (($result['country_name'] === null || $result['country_code'] === null)
            && preg_match('/^Страна:\s*(.+?)\s*\(([^)]+)\)\s*$/mu', $raw, $mm)) {
            $result['country_name'] = trim($mm[1]);
            $result['country_code'] = trim($mm[2]);
        }
        if ($result['region'] === null && preg_match('/^Регион:\s*(.+)$/mu', $raw, $mm)) {
            $trim = trim($mm[1]);
            $result['region'] = $trim !== '' ? $trim : null;
        }
        if ($result['city'] === null && preg_match('/^Город:\s*(.+)$/mu', $raw, $mm)) {
            $trim = trim($mm[1]);
            $result['city'] = $trim !== '' ? $trim : null;
        }
        if (($result['country_name'] === null || $result['country_code'] === null)
            && preg_match('/^Country:\s*(.+)$/mi', $raw, $cm)) {
            $result['country_name'] = trim($cm[1]);
        }

        if ($result['country_name'] === null && $result['country_code'] === null && $result['geo_parse_error'] === null) {
            if (strpos($raw, 'GeoIP') !== false || preg_match('/^2\.\s/m', $raw)) {
                $result['geo_parse_error'] = 'Блок GeoIP найден частично, но строка «Страна: … (код)» не распознана.';
            }
        }
    }
}
