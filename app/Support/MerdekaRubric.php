<?php

namespace App\Support;

/**
 * The Merdeka corner scoresheet: six criteria, a 1–5 scale, weights summing to 100.
 *
 * Hardcoded rather than admin-editable. The contest runs once, and a rubric the
 * panel can edit mid-judging is a rubric that can't be compared across corners.
 *
 * Copy stays in Malay — it is the contest's own wording, and translating the
 * band descriptions is exactly where a rubric loses the precision that makes it
 * a rubric.
 */
final class MerdekaRubric
{
    /** Criterion id => name, weight (% of 100) and the one-line summary. */
    public const CRITERIA = [
        'tema' => [
            'name' => 'Kesesuaian Tema',
            'weight' => 20,
            'hint' => 'Elemen kemerdekaan jelas (Jalur Gemilang, warna & simbol negara), mesej patriotik konsisten, tiada elemen sensitif.',
        ],
        'kreativiti' => [
            'name' => 'Kreativiti & Inovasi',
            'weight' => 20,
            'hint' => 'Idea asli, bahan digunakan secara kreatif, ada elemen menarik (3D, cahaya, gerakan atau interaktif).',
        ],
        'persembahan' => [
            'name' => 'Persembahan Sudut (Pembentangan)',
            'weight' => 20,
            'hint' => 'Wakil faham konsep & mesej, penyampaian jelas dan yakin, persembahan siap dalam 10 minit.',
        ],
        'kos' => [
            'name' => 'Kos Efektif',
            'weight' => 20,
            'hint' => 'Perbelanjaan ≤ RM100 dengan resit, guna bahan kitar semula, hasil berbaloi dengan kos.',
        ],
        'kekemasan' => [
            'name' => 'Kekemasan & Susun Atur',
            'weight' => 10,
            'hint' => 'Binaan rapi, susun atur seimbang, ruang bersih dan tidak menghalang laluan kecemasan.',
        ],
        'impak' => [
            'name' => 'Impak Visual',
            'weight' => 10,
            'hint' => 'Menarik pada pandangan pertama (3 saat), warna & reka bentuk seimbang, dikenali dari jauh.',
        ],
    ];

    public const SCALE_LABELS = [
        5 => 'Cemerlang',
        4 => 'Baik',
        3 => 'Sederhana',
        2 => 'Lemah',
        1 => 'Sangat Lemah',
    ];

    /** What each band actually means, per criterion. This is the real content. */
    public const BANDS = [
        'tema' => [
            5 => 'Tema kemerdekaan sangat kuat & jelas; elemen negara digunakan sepenuhnya, mesej patriotik menonjol di setiap sudut.',
            4 => 'Tema jelas & konsisten; kebanyakan elemen kemerdekaan digunakan dengan baik.',
            3 => 'Tema dapat dikenali tetapi biasa; elemen kemerdekaan asas sahaja.',
            2 => 'Tema kurang jelas; elemen kemerdekaan sedikit, kabur atau tidak konsisten.',
            1 => 'Tema tidak kelihatan atau tersasar; hampir tiada elemen kemerdekaan.',
        ],
        'kreativiti' => [
            5 => 'Idea sangat asli & inovatif; bahan digunakan secara luar biasa, ada elemen menarik (3D, cahaya, gerakan).',
            4 => 'Idea kreatif dengan sentuhan baharu; usaha inovasi jelas kelihatan.',
            3 => 'Idea biasa tetapi kemas; kreativiti pada tahap sederhana.',
            2 => 'Idea kurang asli; banyak meniru rujukan sedia ada.',
            1 => 'Tiada kreativiti; salinan terus atau tiada usaha inovasi.',
        ],
        'persembahan' => [
            5 => 'Pembentangan sangat yakin, jelas & lancar; aktiviti bergerak menarik, selamat dan siap dalam masa.',
            4 => 'Pembentangan baik & jelas; aktiviti melibatkan ahli jabatan dengan baik.',
            3 => 'Pembentangan memadai; penyampaian asas dan aktiviti ringkas.',
            2 => 'Pembentangan kurang jelas atau kurang yakin; aktiviti terhad/lemah.',
            1 => 'Tiada pembentangan berkesan; tersasar atau melebihi masa 10 minit.',
        ],
        'kos' => [
            5 => 'Kos sangat berpatutan (≤ RM100) & resit lengkap; hasil hebat menggunakan bahan kitar semula.',
            4 => 'Kos terkawal & resit dikemukakan; hasil berbaloi dengan perbelanjaan.',
            3 => 'Kos munasabah; hasil setara dengan kos yang dibelanjakan.',
            2 => 'Kos kurang terkawal atau resit tidak lengkap; hasil kurang berbaloi.',
            1 => 'Melebihi had atau tiada resit; membazir dan hasil tidak setimpal.',
        ],
        'kekemasan' => [
            5 => 'Sangat kemas & tersusun; binaan rapi, ruang selamat & seimbang.',
            4 => 'Kemas & teratur; hanya sedikit kekurangan.',
            3 => 'Kekemasan sederhana; susun atur boleh diterima.',
            2 => 'Kurang kemas; ada tampalan/tali terdedah, ruang agak sesak.',
            1 => 'Berselerak & tidak selamat; menghalang laluan.',
        ],
        'impak' => [
            5 => 'Sangat menarik pada pandangan pertama; warna & reka bentuk seimbang, dikenali dari jauh.',
            4 => 'Menarik & seimbang; mudah dikenali.',
            3 => 'Impak sederhana; biasa tetapi kemas.',
            2 => 'Kurang menarik; warna atau reka bentuk tidak seimbang.',
            1 => 'Tiada impak; sukar dikenali sebagai sudut kemerdekaan.',
        ],
    ];

    /** @return list<string> */
    public static function ids(): array
    {
        return array_keys(self::CRITERIA);
    }

    public static function weight(string $criterion): int
    {
        return self::CRITERIA[$criterion]['weight'];
    }

    /** Marks a given band earns on a criterion, e.g. band 4 of 5 on a 20% criterion = 16. */
    public static function earned(string $criterion, int $band): float
    {
        return round($band / 5 * self::weight($criterion), 2);
    }

    /**
     * The scoresheet total, out of 100.
     *
     * Unknown keys are ignored and missing ones score nothing, so a tampered
     * payload can only ever score lower — it can't invent marks.
     *
     * @param  array<string, int|string|null>  $scales
     */
    public static function total(array $scales): float
    {
        $total = 0.0;

        foreach (self::ids() as $criterion) {
            $band = (int) ($scales[$criterion] ?? 0);

            if ($band >= 1 && $band <= 5) {
                $total += self::earned($criterion, $band);
            }
        }

        return round($total, 2);
    }
}
