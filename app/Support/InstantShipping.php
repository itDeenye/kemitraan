<?php

namespace App\Support;

final class InstantShipping
{
    public const COURIER_CODES = ['gosend', 'grab_express', 'borzo'];

    public const VEHICLES = ['motor', 'mobil'];

    public const TIMEZONES = ['WIB', 'WIT', 'WITA'];

    public const MAX_WEIGHT_GRAMS = 40_000;
}
