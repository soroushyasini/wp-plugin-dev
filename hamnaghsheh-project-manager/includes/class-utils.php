<?php
class Hamnaghsheh_Utils
{
    public static function get_project_type_label($value)
    {
        $labels = [
            'residential' => 'ساخت‌وساز مسکونی',
            'commercial' => 'ساخت‌وساز تجاری',
            'renovation' => 'بازسازی',
            'infrastructure' => 'زیرساخت',
            'other' => 'سایر'
        ];
        return $labels[$value] ?? 'نامشخص';
    }
}
