<?php

return [
    'footer_office_name' => env('LIBRARY_FOOTER_OFFICE_NAME', 'ප්‍රාදේශීය ලේකම් කාර්යාලය'),

    'email_alerts' => [
        'enabled' => env('LIBRARY_EMAIL_ALERTS_ENABLED', true),
        'due_date_reminder_days' => (int) env('LIBRARY_DUE_DATE_REMINDER_DAYS', 2),
        'membership_expiry_reminder_days' => (int) env('LIBRARY_MEMBERSHIP_EXPIRY_REMINDER_DAYS', 7),
    ],

    'pradeshiya_sabha_admin_passwords' => [
        'BAL' => env('PRADESHIYA_SABHA_ADMIN_PASSWORD_BAL'),
        'AMB' => env('PRADESHIYA_SABHA_ADMIN_PASSWORD_AMB'),
        'WEL' => env('PRADESHIYA_SABHA_ADMIN_PASSWORD_WEL'),
        'KAR' => env('PRADESHIYA_SABHA_ADMIN_PASSWORD_KAR'),
        'BEN' => env('PRADESHIYA_SABHA_ADMIN_PASSWORD_BEN'),
        'ELP' => env('PRADESHIYA_SABHA_ADMIN_PASSWORD_ELP'),
        'NEL' => env('PRADESHIYA_SABHA_ADMIN_PASSWORD_NEL'),
        'THA' => env('PRADESHIYA_SABHA_ADMIN_PASSWORD_THA'),
        'NAG' => env('PRADESHIYA_SABHA_ADMIN_PASSWORD_NAG'),
        'NIY' => env('PRADESHIYA_SABHA_ADMIN_PASSWORD_NIY'),
        'BAD' => env('PRADESHIYA_SABHA_ADMIN_PASSWORD_BAD'),
        'YAK' => env('PRADESHIYA_SABHA_ADMIN_PASSWORD_YAK'),
        'RAJ' => env('PRADESHIYA_SABHA_ADMIN_PASSWORD_RAJ'),
        'AKM' => env('PRADESHIYA_SABHA_ADMIN_PASSWORD_AKM'),
        'BOP' => env('PRADESHIYA_SABHA_ADMIN_PASSWORD_BOP'),
        'IMA' => env('PRADESHIYA_SABHA_ADMIN_PASSWORD_IMA'),
        'HAB' => env('PRADESHIYA_SABHA_ADMIN_PASSWORD_HAB'),
    ],
];