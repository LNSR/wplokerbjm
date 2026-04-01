---
name: job-copywriter
description: >-
  Membantu menyusun naskah lowongan kerja yang terstruktur, informatif, dan
  mudah ditampilkan di UI JobDetail (WPLokerBJM). Fokus pada:
    - `nama_perusahaan`: formal / resmi
    - bagian lain: gaya narasi yang bersahabat namun ringkas (bullet/nomor)
    - struktur output yang langsung dapat dipakai sebagai konten WYSIWYG
      (HTML/basic markup)
---

# Skill: Job Posting Copywriter (WPLokerBJM)

## Tujuan

Menyusun naskah lowongan kerja yang:

- **mudah dibaca**
- **rapi**
- **sesuai struktur UI `JobDetail.svelte`**
- **optimal ditampilkan di sistem** (mudah disalin-tempel ke editor WYSIWYG)

---

## Struktur Output yang Diharapkan (Field → Komponen)

Output dari skill ini harus memberikan konten yang digunakan pada field berikut
(sesuai `JobDetail.svelte`):

1. **`title`**
   - Judul lowongan (contoh: `Barista & Kitchen`, `Operator Produksi`, `Office Admin`)

2. **`nama_perusahaan`** *(formal)*
   - Nama resmi / brand lengkap (misal: `PT Next Carwash Indonesia`, `Ketemu Studio`, `Everyday Vacuum`)
   - Gunakan kapitalisasi formal dan hindari singkatan kasual
   - Jika tidak tersedia dalam sumber input, abaikan field ini

3. **`ringkasanPekerjaan`** (summary rows: jenis pekerjaan, pendidikan, pengalaman, lokasi, gaji, dll.)
   - `useSummaryJob` akan memprosesnya menjadi baris ringkasan
   - Contoh ringkas:
     - Jenis Pekerjaan: Full-time, On-site
     - Pendidikan: SMA/SMK
     - Pengalaman: 1+ tahun (opsional)
     - Lokasi: Banjarmasin

4. **`tentang_perusahaan`**
   - Paragraf singkat (1–2 kalimat) menjelaskan profil perusahaan, lini bisnis, dan nilai inti
   - Nada profesional dengan nuansa manusiawi

5. **`deskripsi_pekerjaan`**
   - Gunakan bullet/nomor untuk tugas utama
   - Pisahkan setiap poin agar mudah dibaca
   - Poin yang menjelaskan pekerjaan sehari-hari, tugas, tanggung jawab langsung masuk di sini
   - Contoh:
     - Mengelola operasional mesin cuci mobil
     - Melaksanakan quality check sebelum kendaraan diserahkan ke pelanggan

6. **`persyaratan`**
   - Gunakan bullet/nomor (•, -, 1., 2., dsb.)
   - Cantumkan kriteria wajib dan preferensi
   - Poin yang berfokus pada kualifikasi kandidat, pengalaman, usia, pendidikan, dan syarat administratif masuk di sini
   - Hindari duplikasi dengan `deskripsi_pekerjaan` (poin tugas operasional/harian harus di `deskripsi_pekerjaan`, bukan di `persyaratan`)
   - Jika input mencampur tugas dan kualifikasi (contoh: "Bertanggung jawab atas ..." vs "Mampu melakukan ..."), prioritaskan:
     - tugas operasional/harian ke `deskripsi_pekerjaan`
     - kriteria kandidat ke `persyaratan`
   - Contoh:
     1. Domisili Banjarmasin (wajib)
     2. Pria/Wanita, usia maksimal 30 tahun
     3. Minimal 2 tahun pengalaman kerja relevan

7. **`cara_melamar`**
   - Sampaikan singkat dan jelas
   - Contoh:
     - Kirim CV & portofolio (PDF) via WhatsApp: `0856 5496 9738`
     - Subjek: `Nama – Posisi yang dilamar`

8. **`benefit`** (opsional)
   - Jika tersedia, uraikan hak karyawan atau fasilitas pendukung
   - Gunakan bullet ringkas

9. **Kontak / Sosial Media**
   - Jika tersedia, cantumkan informasi kontak (telepon/WA/email)
   - Media sosial (Instagram, Telegram, dsb.) dapat ditambahkan secara ringkas

---

## Tips Nada & Format

- **Nama perusahaan**: gunakan format formal (huruf kapital sesuai standar, hindari slang)
- **Bagian lain**: dapat menggunakan gaya bersahabat namun profesional
- **Gunakan bullet/nomor** saat ada lebih dari satu poin
- **Pecah kalimat panjang** menjadi poin yang singkat (1 ide = 1 poin)
- **Hindari pengulangan informasi** (DRY: setiap informasi hanya muncul di satu field paling relevan)
- **Gunakan hanya informasi dari input**; tidak menambah data asumsi baru
- **Field yang tidak ada dari sumber input** harus dihilangkan
- **Output hanya berisi field nyata** sesuai konten input; jangan menyertakan field tambahan

- **Gabungkan informasi multi-flyer/posisi** dalam satu field:
  - Jika ada beberapa posisi dalam satu input, gabungkan isi masing-masing field (misalnya semua `persyaratan` dalam satu field `persyaratan`)
  - Untuk `title` dan `nama_perusahaan`, gunakan separator ` | ` jika lebih dari satu posisi/perusahaan
    (contoh: `Crew & Kasir | Warung Bakso`)
  - Di field lain, pisahkan dengan subjudul jelas (contoh: **Crew Warung Bakso:**, **Kasir Warung Bakso:**)
  - Jika ada poin identik antar posisi, tulis sekali saja dengan keterangan posisi relevan
  - Jangan ulang informasi yang sama di beberapa field atau bagian
  - Tujuan: hasil langsung siap dipakai di WYSIWYG dan mudah dibaca

---

## Contoh Output Gabungan (Multi Flyer/Posisi)

**Input (OCR/paste bebas):**
> We're Hiring
> Crew Warung Bakso
> Syarat: Pria, Usia 18-28, Domisili Banjarmasin, SMA/sederajat, dst.
> Kasir Warung Bakso
> Syarat: Wanita, Usia 18-28, Domisili Banjarmasin, Bisa komputer, SMK/sederajat, dst.

**Output yang diharapkan:**

- `title`: Crew & Kasir | Warung Bakso
- `persyaratan`:
  - Berlaku untuk semua posisi:
    1. Usia 18–28 tahun
    2. Domisili Banjarmasin

  - **Crew Warung Bakso:**
    1. Pria
    2. Pendidikan minimal SMA/sederajat

  - **Kasir Warung Bakso:**
    1. Wanita
    2. Bisa mengoperasikan komputer
    3. Pendidikan minimal SMK/sederajat

---

### Contoh DRY (Don't Repeat Yourself) untuk Multi-Posisi

Jika ada syarat sama di beberapa posisi, tidak perlu ulang pada tiap sub-posisi. Gabungkan di bagian "Berlaku untuk semua posisi" lalu letakkan syarat unik di tiap sub-posisi.

**Contoh:**

- `persyaratan`:
  - Berlaku untuk semua posisi:
    1. Usia 18–28 tahun
    2. Domisili Banjarmasin

  - **Crew Warung Bakso:**
    1. Pria
    2. Pendidikan minimal SMA/sederajat

  - **Kasir Warung Bakso:**
    1. Wanita
    2. Bisa mengoperasikan komputer
    3. Pendidikan minimal SMK/sederajat

---

## OCR → Struktur (Guidance)

Saat menerima input OCR, asumsikan format sebagai berikut:

- Judul utama → `title`
- Kata “Syarat” / “Persyaratan” → `persyaratan`
- Kata “Hubungi” / “WA” → `cara_melamar` / `kontak`
- “Domisili …”, “Usia …”, “Pengalaman …” → keluar sebagai bullet di `persyaratan`
- Frase “Kirim CV ke …” → `cara_melamar`

> Tujuan: ekstrak teks mentah, rapikan menjadi field jelas, dan keluarkan konten yang siap dipakai untuk job posting.

> Penting: field yang tidak terdapat di input harus dihilangkan; tidak menambah atau mengulang informasi.

---


## Catatan Tambahan untuk AI Agent (Penerapan Otomatis)

- Jika ada web resmi perusahaan (misal `butterfly.management`) atau social media resmi, WAJIB mencari dan mengisi:
  - `social_media`: `instagram`, `tiktok`, `whatsapp`, `website`, dan platform lain yang relevan
  - `cara_melamar`: format “Hubungi via WA +62 856-9516-8825 / DM Instagram @butterfly_management”
- Jika teks lowongan menyebut benefit dan lokasi kantor, peta menjadi `benefit` dan `ringkasanPekerjaan`.
- Jika tersedia alamat, WAJIB mencari di internet dan menyisipkan link Google Maps di bagian `lokasi` atau `kontak`.
- Selalu gunakan data dari sumber yang terverifikasi (website resmi, landing page, Google Maps, media sosial resmi, dsb). Jika data tidak ditemukan di input, lakukan pencarian aktif di internet menggunakan tools (`runSubagent`, `fetch_webpage`, dsb) hingga mendapatkan hasil maksimal.
- Jangan menambahkan bidang yang tidak tercantum dalam input asli KECUALI jika ditemukan dari sumber daring yang kredibel.

### Alur Pemanggilan Tools yang Disarankan (WAJIB LEBIH AGRESIF)

1. Jika data tidak lengkap di input, SEGERA gunakan `runSubagent` (Explore) untuk mencari data perusahaan, alamat, sosial media, dan link Google Maps.
2. Gunakan `fetch_webpage` untuk mengambil data dari situs resmi perusahaan, Google Maps, dan media sosial.
3. Jika alamat kantor tersedia, cari link Google Maps yang paling relevan dan validasikan hasilnya.
4. Ulangi pencarian hingga data penting (kontak, sosial media, Google Maps, website) benar-benar ditemukan atau dipastikan tidak ada.
5. Prioritaskan hasil pencarian internet untuk melengkapi field yang kosong atau kurang jelas.



---

## Contoh Output (Ringkas)

**Input (OCR/paste bebas):**
> WE ARE HIRING!
> BARISTA & KITCHEN
> Syarat :
> - Domisili Banjarmasin
> - Laki Laki/Perempuan
> - Usia max 30 tahun
> - Punya 2 tahun pengalaman
> Hubungi nomor +62 877-5713-2074

**Output yang diharapkan (format field):**

- `title`: `Barista & Kitchen`
- `deskripsi_pekerjaan`:
  - Bertugas menyiapkan minuman & makanan sesuai standar
  - Menjaga kebersihan area kerja dan peralatan
- `persyaratan`:
  1. Domisili Banjarmasin
  2. Pria/Wanita, usia maksimal 30 tahun
  3. Minimal 2 tahun pengalaman kerja relevan
- `cara_melamar`: `Kirim CV & portofolio (PDF) via WhatsApp ke +62 877-5713-2074`
