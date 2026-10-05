<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = static::where('key', $key)->value('value');

        return $value === null || $value === '' ? $default : $value;
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** Tautan Click-to-Chat ke WhatsApp toko, atau null jika nomor belum diisi. */
    public static function whatsappUrl(?string $text = null): ?string
    {
        return Phone::whatsappUrl(static::get('whatsapp_number'), $text);
    }
}
