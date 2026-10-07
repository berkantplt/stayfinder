<?php

namespace App\Services\BulkImport;

use RuntimeException;

/**
 * Toplu içe aktarımda tek bir satırı/acentayı düşüren, kullanıcıya olduğu gibi
 * gösterilebilir (dostane) hata. Komut bunu yakalar, raporlar ve sıradakine geçer.
 */
class BulkImportException extends RuntimeException {}
