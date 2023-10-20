<?php
// Assets URL
define('assetsURL', '**********');

// Assets Credentials
define('assetsUSERNAME', '**********');
define('assetsPASSWORD', '**********');

// Search path for Proveedores Images
define('assetsSEARCH_PATHS', ['/SAIEP/PROVEEDORES/CARGA AGENCIAS', '/SAIEP/PROVEEDORES/CARGA GENERAL', '/SAIEP/PROVEEDORES/CARGA INTERNA', '/SAIEP/PROVEEDORES/REPROCESAR']);

// Search path for La Anónima Images
define('assetsLA_PATH', '/SAIEP/PRODUCTOS');

// Batch path for Images
define('assetsBATCH_PATH', '/tmp');

// Duplicate path
define('assetsDUPLICATE_PATH', '/SAIEP/PROVEEDORES/NO PROCESADAS/DUPLICADOS');

// Invalid Format path
define('assetsINVALID_PATH', '/SAIEP/PROVEEDORES/NO PROCESADAS/FORMATOS NO VALIDOS');

// No EAN path
define('assetsNO_EAN_PATH', '/SAIEP/PROVEEDORES/NO PROCESADAS/NO EAN');

// No @noimage- path
define('assetsNO_NOIMAGE_PATH', '/SAIEP/PROVEEDORES/NO PROCESADAS/NO @NOIMAGE-');

// Metadata Fields
define('eanCODE', 'cf_ean');
define('glaciarCODE', 'cf_codart');

// Image Name Pattern
define('imageNamePATTERN', '@noimage-');

// Source path (Copy)
define('assetsCOPY_SOURCE__PATH', '/SAIEP/PROVEEDORES/ETIQUETADO');

// Target path (Copy)
define('assetsCOPY_TARGET__PATH', '/SAIEP/PROVEEDORES/CARGA GENERAL');

// New Target path, witch obtein Assets from  > assetsCOPY_SOURCE__PATH (copy) and assetsCOPY_TARGET__PATH (copy)
define('etradeAssetsCOPY_TARGET__PATH', '/SAIEP/PROVEEDORES/ETRADE');

// File register assets already copied
define('assetsCOPIED_FILE', __DIR__ . '/filesCopied.txt');

// Log File new Etrade log
define('logEtradeFILE', '/opt/assets/assetsEtradeLog.log');

// Log File 
define('logFILE', '/opt/assets/assetsLog.log');

?>
