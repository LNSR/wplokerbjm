---
name: job-copywriter
description: >-
  Membantu menulis lowongan kerja yang rapi, jelas, dan mudah ditampilkan di UI
  JobDetail (WPLokerBJM). Fokus pada:
    - `nama_perusahaan` : formal / resmi
    - bagian lain : nada conversational, ringkas, dan ringkas dengan bullet/nomor
    - struktur output agar bisa langsung dipakai sebagai konten WYSIWYG (HTML/basic markup)
---

# Skill: Job Posting Copywriter (WPLokerBJM)

## Tujuan

Bantu membuat naskah lowongan kerja yang:

- **mudah dibaca**
- **rapi**
- **sesuai struktur UI `JobDetail.svelte`**
- **optimal untuk ditampilkan di dalam sistem** (disiapkan agar mudah disalin-tempel ke editor WYSIWYG)

---

## Struktur Output yang Diharapkan (Field → Komponen)

Output dari skill ini harus memberikan konten yang dapat digunakan pada field berikut (sesuai `JobDetail.svelte`):

1. **`title`**
   - Judul lowongan (contoh: `Barista & Kitchen`, `Operator Produksi`, `Office Admin`)

2. **`nama_perusahaan`** *(formal)*
   - Nama resmi / brand lengkap (misal: `PT Next Carwash Indonesia`, `Ketemu Studio`, `Everyday Vacuum`)
   - Gunakan kapitalisasi formal dan hindari singkatan yang terlalu kasual.
   - Jika tidak tercantum di sumber input, biarkan dihilangkan (skip field).

3. **`ringkasanPekerjaan`** (summary rows: jenis pekerjaan, pendidikan, pengalaman, lokasi, gaji, dll.)
   - Di dalam `useSummaryJob` akan diproses jadi baris ringkasan.
   - Usahakan tetap ringkas, contoh:
     - Jenis Pekerjaan: Full-time, On-site
     - Pendidikan: SMA/SMK
     - Pengalaman: 1+ tahun (opsional)
     - Lokasi: Banjarmasin

4. **`tentang_perusahaan`**
   - Paragraf pendek (1–2 kalimat) yang menjelaskan siapa perusahaan, apa bisnisnya, dan nilai intinya.
   - Nada: profesional, tetapi tetap “human”.

5. **`deskripsi_pekerjaan`**
   - Gunakan bullet/nomor untuk tugas utama.
   - Pisahkan setiap poin agar mudah dipahami.
   - Contoh:
     - Menangani operasional mesin cuci mobil.
     - Melakukan quality check sebelum kendaraan diserahkan ke pelanggan.

6. **`persyaratan`**
   - Pastikan memakai bullet/nomor (•, -, 1., 2., dsb.).
   - Sertakan kriteria wajib & preferensi.
   - Contoh:
     1. Domisili Banjarmasin (wajib)
     2. Pria/Wanita, usia maksimal 30 tahun
     3. Minimal 2 tahun pengalaman kerja relevan

7. **`cara_melamar`**
   - Tulis singkat dan jelas (karena sudah self-explanatory).
   - Contoh:
     - Kirim CV & portofolio (PDF) via WhatsApp: `0856 5496 9738`
     - Subjek: `Nama – Posisi yang dilamar`

8. **`benefit` (opsional)**
   - Jika ada, tuliskan poin benefit/hak karyawan.
   - Gunakan bullet ringkas.

9. **Kontak / Sosial Media**
   - Bila tersedia, uraikan kontak yang dapat dihubungi (telp/WA/email).
   - Media sosial boleh ditambahkan (Instagram, Telegram, dsb.) dengan format singkat.

---


## Tips Nada & Format


- **Nama perusahaan: formal** (huruf besar benar, hindari slang).
- **Semua bagian lain: tone friendly / conversational** — tetap pastikan informasi penting jelas.
- **Gunakan bullet/nomor** jika ada lebih dari 1 poin.
- **Jika teks panjang**, pecah menjadi beberapa poin (lebih baik 1 poin = 1 ide).
- **Jangan ulang-ulang informasi** di beberapa bagian (ikuti prinsip _Don't Repeat Yourself_ / DRY: setiap informasi hanya muncul di satu field yang paling relevan).
- **Hanya gunakan informasi yang ada di sumber input.** Jangan menambahkan paragraf/isi baru yang tidak disebutkan dalam teks OCR.
- **Jika suatu field/section (misal: deskripsi_pekerjaan, benefit, kontak, sosial media) tidak ada di sumber input, OMISI field tersebut dari output.**
- Output harus hanya berisi field yang memang ada di sumber input, tanpa menambah atau mengulang informasi.

 - **Gabungkan semua flyer/posisi ke dalam satu field per bagian:**
    - Jika ada beberapa flyer/posisi dalam satu input, SELALU gabungkan seluruh informasi ke dalam satu field (misal: semua `persyaratan` dari berbagai posisi digabung dalam satu field `persyaratan`, dst).
    - Untuk field `title` dan `nama_perusahaan`, gunakan separator " | " jika ada lebih dari satu posisi/perusahaan. Contoh: `Crew & Kasir | Warung Bakso`.
    - Untuk membedakan tiap posisi di field lain, gunakan subjudul/label yang jelas di dalam field (misal: **Crew Warung Bakso:**, **Kasir Warung Bakso:**, dst), lalu beri bullet/nomor di bawahnya.
    - Jika ada poin yang identik di beberapa posisi, cukup tulis sekali saja (DRY), lalu beri keterangan posisi mana saja yang relevan.
    - Jangan pernah mengulang informasi yang sama di beberapa field atau bagian.
    - Tujuan: output siap copy-paste ke WYSIWYG, mudah dibaca, dan tidak membingungkan user.

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

Jika ada syarat yang sama di beberapa posisi, JANGAN tulis ulang di setiap sub-posisi. Gabungkan di bagian "Berlaku untuk semua posisi". Hanya syarat unik yang ditulis di bawah masing-masing posisi.

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

---


## OCR → Struktur (Guidance)

Saat menerima input dari OCR (teks hasil scan/poster), asumsikan biasanya seperti ini:

- Judul besar → `title`
- Kata “Syarat” / “Persyaratan” → map ke `persyaratan`
- Kata “Hubungi” / “WA” → map ke `cara_melamar` / `kontak`
- “Domisili …”, “Usia …”, “Pengalaman …” → pisahkan jadi bullet di `persyaratan`
- Jika ada frase “Kirim CV ke …”, masukkan ke `cara_melamar`

> Goal: Ambil teks mentah, rapikan menjadi field yang jelas, lalu keluarkan dalam format yang siap dipakai untuk mengisi job posting.

> **PENTING:** Jika suatu field tidak ada di sumber input, OMISI field tersebut dari output. Jangan menambah, mengulang, atau mengisi field dengan asumsi.

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
