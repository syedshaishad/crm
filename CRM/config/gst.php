<?php

return [
    'enable_gst' => (bool) env('TAX_ENABLE_GST', true),
    'default_gst_rate' => (float) env('TAX_DEFAULT_GST_RATE', 18),
    'cgst_sgst_split' => (bool) env('TAX_CGST_SGST_SPLIT', true), // intra-state split; else IGST
    'rates' => [0, 5, 12, 18, 28],
];
