# Foody

Sistem pengurusan rekod restoran menggunakan **native PHP**, MariaDB, HTML, CSS dan JavaScript. Projek ini berjalan secara lokal melalui XAMPP tanpa framework PHP atau proses build frontend.

## Fungsi tersedia

| Bahagian | Fungsi |
| --- | --- |
| Login / Logout | Pengesahan akaun dan pengurusan sesi. |
| Main Page | Pilih restoran dan buka halaman servis mengikut tempoh pilihan pengguna. |
| Service | Masukkan dan simpan jumlah RM untuk 7 hari (Weekly) atau 4 minggu (Monthly). |
| Report | Simpan jumlah laporan secara berasingan mengikut restoran dan tempoh. |
| Commission | Tambah atau kemas kini komisen, penalti dan jumlah kos penghantaran. |
| My Profile | Kemas kini nama, e-mel dan kata laluan. |
| My Restaurant | Kemas kini tiga restoran sedia ada; dua restoran setiap halaman. |
| Order List | Tambah pesanan manual, tapis restoran, pagination dan kemas kini status. |
| Settings | Pilihan restoran, tempoh, bilangan pesanan setiap halaman, format tarikh dan tema. |
| Help Center | Panduan penggunaan serta penerangan ralat. |

Sidebar mengikuti scroll dan berada di tengah skrin. Pada skrin rendah, menu sidebar boleh discroll untuk mencapai semua butang.

## Keperluan

- XAMPP dengan Apache dan MariaDB.
- PHP dengan sambungan `mysqli` / `mysqlnd` dan `mbstring`. Pembangunan dan ujian projek menggunakan PHP 8.4.
- Apache `mod_rewrite` dan `AllowOverride All` untuk peraturan `.htaccess`.
- Node.js 24 untuk menjalankan ujian JavaScript, HTTP dan browser; tidak diperlukan untuk menggunakan sistem.

## Setup localhost

### 1. Letakkan projek dalam XAMPP

Letakkan folder projek di:

```text
C:\xampp\htdocs\Foody
```

Hidupkan **Apache** dan **MySQL** melalui XAMPP Control Panel. Servis MySQL dalam pemasangan ini menggunakan MariaDB.

### 2. Sediakan database

1. Buka `http://localhost/phpmyadmin/`.
2. Cipta database bernama `foody` dengan character set `utf8mb4`.
3. Pilih database tersebut dan import [database/foody.sql](database/foody.sql).

Fail ini mengandungi semua skema, perubahan tetapan tema dan nama awal tiga restoran. Import berulang mengekalkan rekod sedia ada serta butiran restoran yang telah diedit. Database yang sudah siap tidak perlu diimport semula hanya kerana lokasi fail SQL berubah.

**Fail SQL ini bukan backup data semasa.** Akaun, pesanan dan rekod kewangan daripada pemasangan lain tidak disertakan. Gunakan fungsi Export dalam phpMyAdmin jika memerlukan backup rekod tersebut.

### 3. Semak sambungan database

Sambungan dalam `service_functions.php` membaca environment variables berikut:

| Variable | Nilai lalai lokal |
| --- | --- |
| `FOODY_DB_HOST` | `localhost` |
| `FOODY_DB_PORT` | `3306` |
| `FOODY_DB_NAME` | `foody` |
| `FOODY_DB_USER` | `root` |
| `FOODY_DB_PASSWORD` | Kosong |

Jika menggunakan konfigurasi lain, tetapkan variables pada proses Apache dan terminal CLI yang berkaitan. Projek tidak membaca fail `.env` secara automatik. Nilai lalai di atas adalah untuk setup XAMPP lokal.

### 4. Cipta akaun pertama

Buka PowerShell di folder projek:

```powershell
Set-Location C:\xampp\htdocs\Foody
$env:Path = 'C:\xampp\php;' + $env:Path
.\scripts\create-account.ps1
```

Masukkan nama, e-mel, kata laluan dan pengesahan apabila diminta. Kata laluan baharu mesti sepanjang **12 hingga 72 bytes**.

Skrip ini hanya membenarkan penciptaan akaun pertama apabila tiada akaun dalam database. Jika akaun sudah wujud, gunakan akaun tersebut. Tiada akaun atau kata laluan lalai dibekalkan melalui fail SQL, dan tiada pendaftaran awam.

### 5. Buka sistem

```text
http://localhost/Foody/
```

Alamat tersebut membuka Main Page jika sudah login, atau membawa pengguna ke Login. Login juga boleh dibuka terus di `http://localhost/Foody/login.php`.

## Cara data disimpan

- **Service / Report:** butang `Calculation` menyimpan semua jumlah bagi restoran dan tempoh yang dipilih, kemudian membuka halaman komisen.
- **Commission:** `Add` mencipta rekod; `Update` mengubah rekod sedia ada bagi restoran, modul dan tempoh berkenaan.
- **My Profile / My Restaurant / Settings:** perubahan disimpan apabila borang dihantar melalui butang simpan berkaitan. `Reset to Defaults` dalam Settings menyimpan semula pilihan lalai.
- **Order List:** `Add` mencipta pesanan berstatus Pending. `Update` menukar Pending kepada Completed atau Cancelled; kedua-duanya status akhir.
- **Delete pada borang Service / Report / Commission:** mengembalikan nilai asal borang ketika halaman dibuka; tidak memadam rekod database.

Pengiraan wang menggunakan integer cents. Input menerima sehingga dua tempat perpuluhan. NETT dikira semula pada server:

```text
NETT Commission = Total Commission - Penalties - Total Delivery Cost
```

Medan `Delivery per Services` mewakili **jumlah kos penghantaran dalam RM**, bukan bilangan penghantaran. NETT boleh menjadi negatif jika potongan melebihi komisen.

## Skop semasa

- Semua pengguna yang login berkongsi akses kepada rekod perniagaan; role-based access belum dilaksanakan.
- Tiga restoran sedia ada boleh dikemas kini. Tambah dan padam restoran belum tersedia.
- Angka `Services Completed` dan `Total Reports` masih angka contoh.
- Weekly dan Monthly masing-masing menyimpan satu set nilai bagi setiap restoran dan modul; belum ada sejarah mengikut minggu atau bulan bertarikh.
- Total Commission diisi secara manual. Jumlah Service / Report tidak dipindahkan secara automatik sebagai komisen.
- Pesanan tidak mengubah angka contoh Service / Report atau nilai komisen secara automatik.
- Pilihan Settings disimpan mengikut akaun. Light menyokong panel Solid / Transparent; Dark menggunakan panel solid. Login mengingati tema terakhir pada browser tersebut.

## Susunan kod

```text
database/foody.sql       Skema database bersepadu dan naik taraf tetapan
scripts/                Skrip CLI untuk penciptaan akaun pertama
tests/                  Ujian PHP, JavaScript, HTTP dan browser
*_functions.php         Logik pengesahan, pengiraan dan akses database
*_controller.php        Pemprosesan request bagi modul berkaitan
sidebar.php / sidebar.js Sidebar bersama dan pelarasan kedudukan
appearance_functions.php Pembacaan pilihan tema dan cookie penampilan
theme.css / account.css  Gaya tema dan halaman akaun
.htaccess               Halaman permulaan dan sekatan akses fail dalaman
```

Database menggunakan prepared statements. Borang yang mengubah data mempunyai perlindungan CSRF, dan kemas kini berkaitan menolak rekod atau borang lapuk. Kata laluan disimpan sebagai hash; perubahan e-mel atau kata laluan memerlukan kata laluan semasa dan membatalkan sesi lama.

## Menjalankan ujian

Jalankan dari folder projek selepas menyediakan database. Tambahkan PHP XAMPP ke `PATH` seperti langkah penciptaan akaun di atas.

```powershell
php tests/auth_test.php
php tests/service_test.php --database
php tests/report_test.php --database
php tests/commission_test.php --database
php tests/restaurant_test.php
php tests/order_test.php
php tests/settings_test.php
php tests/appearance_test.php
node tests/service_js_test.mjs
node tests/commission_js_test.mjs
```

Ujian database di atas menggunakan temporary tables untuk mengasingkan rekod ujian daripada jadual sebenar.

Untuk ujian HTTP, Apache mesti berjalan di `http://localhost/Foody/`:

```powershell
php tests/run_authenticated_tests.php
```

Runner HTTP mencipta akaun dan pesanan sementara, menguji perubahan nama restoran Jamilah, kemudian membersihkan fixture dan memulihkan butiran restoran. Jalankan pada pemasangan pembangunan tanpa penyuntingan serentak. Ujian HTTP dihadkan kepada database lokal bernama `foody`.

Ujian tema dan sidebar dalam browser memerlukan Edge headless berasingan pada port debugging `9237`. Contoh PowerShell:

```powershell
$foodyBrowserProfile = Join-Path $env:TEMP ('foody-browser-' + [guid]::NewGuid().ToString('N'))
$foodyBrowserArgs = @(
    '--headless=new'
    '--remote-debugging-port=9237'
    ('--user-data-dir="' + $foodyBrowserProfile + '"')
    '--no-first-run'
    '--no-default-browser-check'
    'about:blank'
)
$foodyBrowser = Start-Process -FilePath 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe' -ArgumentList $foodyBrowserArgs -WindowStyle Hidden -PassThru
try {
    php tests/run_authenticated_tests.php --appearance-browser
} finally {
    Stop-Process -Id $foodyBrowser.Id -ErrorAction SilentlyContinue
}
```

Laraskan lokasi Edge jika pemasangan berbeza. Screenshot ujian disimpan di folder `foody-theme-screenshots` dalam direktori sementara Windows.

## Penyelesaian masalah

- **Database atau login unavailable:** pastikan MariaDB berjalan, skema sudah diimport dan konfigurasi Apache / CLI menunjuk ke database yang sama. Semak log PHP / Apache untuk butiran teknikal.
- **Form expired / record changed:** reload halaman, semak nilai terbaru dan hantar semula perubahan yang dikehendaki.
- **Too many attempts:** tunggu sehingga 15 minit sebelum mencuba lagi.
- **Kembali ke Login:** sesi tamat selepas 30 minit tidak aktif atau 12 jam, atau dibatalkan selepas perubahan e-mel / kata laluan.
- **HTTP 500 selepas setup Apache:** semak sokongan `mod_rewrite` dan `AllowOverride All` bagi direktori projek.
- **Fail SQL / skrip / ujian mendapat 404 melalui browser:** ini sekatan yang disengajakan. Import SQL melalui phpMyAdmin dan jalankan skrip serta ujian melalui terminal.
