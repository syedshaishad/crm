<?php

/*
 * Files that must ship encoded in the protected build.
 *
 * This is the licence/update gate boundary, not the whole app. Encoding only
 * the crypto class would be pointless: a nuller patches the caller instead.
 * Every file that decides "is this install allowed?" — and every place that
 * wires that decision into the request lifecycle — belongs here.
 *
 * Keep this list in sync if you add a new licence check or move one.
 */
return [
    'bootstrap/app.php',                              // middleware alias registration
    'routes/web.php',                                 // the ['installed','license'] route group
    'app/Http/Middleware/CheckLicense.php',           // the gate itself
    'app/helpers.php',                                // module_visible() → moduleAllowed()
    'modules/Licensing/LicenseManager.php',           // the verdict
    'modules/Licensing/LicenseCertificate.php',       // signature + binding checks
    'modules/Licensing/TrustRoot.php',                // pinned keys
    'modules/Licensing/LicensifyClient.php',          // transport / protocol
    'modules/Updater/PackageSigner.php',              // manifest signature verification
    'modules/Updater/UpdateManager.php',              // verifyPackage() before apply()
    'config/trust_root.php',                          // public-key pins
];
