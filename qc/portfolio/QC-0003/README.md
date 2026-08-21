# QC-0003 — bug dan standardisasi searchable dropdown Portfolio

| Field                     | Nilai                              |
| ------------------------- | ---------------------------------- |
| ID                        | `QC-0003`                          |
| Prefix                    | `portfolio-dropdown`               |
| Status                    | `open`                             |
| Severity                  | `major`                            |
| Tanggal/waktu pengujian   | `2026-08-20 14:06 WIB`             |
| Reviewer                  | Fatoni                             |
| Persona/role              | User THC                           |
| Halaman atau URL produksi | https://deploythc.web.id/portfolio |
| Browser/device            | Chrome / laptop Windows            |
| GitHub Issue              | [Perbaiki Portfolio Status Risiko dan searchable select](https://github.com/thcjatim-bit/project-monitoring-system/issues/91) |

## Ringkasan

Pada halaman **Portfolio Cockpit**, dropdown **Status risiko** tidak dapat diklik/dibuka sehingga user tidak dapat memilih filter berdasarkan status risiko.

Selain memperbaiki bug tersebut, seluruh dropdown/filter pada aplikasi perlu menggunakan interaction pattern yang konsisten berupa **searchable dropdown / searchable select** seperti pada bukti referensi.

Dropdown yang memiliki pilihan data harus memungkinkan user:

1. klik dropdown;
2. melihat daftar pilihan;
3. mengetik kata kunci;
4. daftar otomatis terfilter;
5. memilih hasil menggunakan mouse;
6. memilih menggunakan keyboard;
7. menghapus/reset pilihan bila diperbolehkan;
8. menggunakan scroll apabila jumlah pilihan panjang.

Targetnya adalah membuat satu komponen dropdown reusable sehingga perilaku filter tidak berbeda-beda antar halaman.

## Langkah reproduksi

1. Buka `https://deploythc.web.id/portfolio`.
2. Login sebagai **User THC**.
3. Buka menu **Portfolio**.
4. Temukan bagian **Filter cakupan**.
5. Klik dropdown **Status risiko**.
6. Perhatikan bahwa dropdown tidak dapat dibuka/dipilih sebagaimana mestinya.
7. Bandingkan dengan dropdown searchable pada bukti referensi.
8. Periksa juga dropdown:

   * Project;
   * Mitra;
   * Periode;
   * Status risiko.

## Hasil aktual

Dropdown **Status risiko** tidak dapat digunakan dengan normal.

Akibatnya user tidak dapat mengatur filter risiko seperti:

* Hijau;
* Kuning;
* Merah;
* N/A;
* atau nilai risiko lain yang tersedia dari backend.

Dropdown lainnya masih menggunakan pola select biasa dan belum memiliki pengalaman pencarian yang konsisten untuk daftar dengan pilihan banyak.

Dengan bertambahnya data Project, Mitra, Warehouse, Material, PoP, User, dan master data lainnya, native/simple dropdown akan semakin sulit digunakan.

## Hasil yang diharapkan

### 1. Perbaiki bug Status risiko

Dropdown **Status risiko** harus:

* dapat diklik;
* dapat dibuka;
* menampilkan opsi yang authorized;
* memungkinkan opsi dipilih;
* menutup setelah selection dilakukan;
* memperbarui state filter dengan benar;
* bekerja dengan tombol `Terapkan filter`;
* dapat di-reset melalui `Reset filter`;
* tidak tertutup/terhalang elemen lain.

Investigasi akar masalah, termasuk tetapi tidak terbatas pada:

* `disabled` state yang salah;
* overlay;
* `pointer-events`;
* `z-index`;
* event handler;
* controlled/uncontrolled state;
* hydration;
* component state;
* invalid HTML nesting;
* CSS stacking context;
* permission/read-scope handling;
* data options kosong;
* error JavaScript.

Jangan hanya memberikan workaround CSS tanpa memastikan akar masalahnya.

---

## Standard searchable dropdown

Seluruh dropdown dengan dataset pilihan harus mengikuti pola seperti bukti referensi:

```text
┌──────────────────────────────────────┐
│ Pilih Project                    × ▴ │
├──────────────────────────────────────┤
│ project abc                          │
├──────────────────────────────────────┤
│ Project ABC - [PRJ00001]             │
│ Project ABD - [PRJ00002]             │
│ Project XYZ - [PRJ00003]             │
│ ...                                  │
│                              scrollbar│
└──────────────────────────────────────┘
```

atau behavior UI yang setara menggunakan component/library yang sudah tersedia dalam project.

### Interaction

Saat dropdown dibuka:

* fokus langsung dapat berpindah ke search input;
* user dapat langsung mengetik;
* filtering berjalan secara case-insensitive;
* search sebaiknya mencocokkan nama dan identifier/code bila tersedia.

Contoh:

```text
Input:
warehouse dep

Result:
Warehouse Depok - [WHJWB21060001]
```

Pencarian juga dapat menggunakan code:

```text
Input:
21060001

Result:
Warehouse Depok - [WHJWB21060001]
```

---

## Dropdown yang wajib menggunakan pola konsisten

Minimal perbaiki seluruh dropdown pada **Portfolio Cockpit**:

### Project

Tampilan disarankan:

```text
<Nama Project> - [<Project Code/ID>]
```

Search berdasarkan:

* nama;
* code/ID.

### Mitra

Tampilan disarankan:

```text
<Nama Mitra> - [<Mitra Code/ID>]
```

jika identifier tersedia dan relevan.

Search berdasarkan:

* nama;
* code/ID.

### Periode

Tetap boleh menggunakan pilihan bulan/periode yang terstruktur.

Search input boleh tidak ditampilkan apabila jumlah pilihan sangat kecil dan component design system mendukung mode non-searchable.

Namun visual component harus tetap sama.

### Status risiko

Pilihan harus dapat diklik dan dipilih.

Contoh:

```text
Semua status risiko
Hijau
Kuning
Merah
N/A
```

Untuk pilihan sedikit seperti Status risiko, search field boleh dinonaktifkan bila tidak berguna, tetapi **component, keyboard behavior, focus, popup, dan visual style tetap konsisten dengan dropdown lainnya**.

---

## Berlaku sebagai component reusable

Jangan membuat implementasi khusus hanya untuk halaman Portfolio.

Buat atau reuse satu component, misalnya secara konseptual:

```text
SearchableSelect
```

atau nama sesuai konvensi project.

Component harus dapat menerima kebutuhan seperti:

```text
options
value
onChange
placeholder
searchable
clearable
disabled
loading
emptyMessage
getOptionLabel
getOptionValue
```

Nama API component tidak harus sama persis dengan contoh di atas.

Prioritaskan pola arsitektur existing.

Tujuan utamanya:

```text
Satu component
      │
      ├── Project
      ├── Mitra
      ├── Warehouse
      ├── Material
      ├── PoP
      ├── User
      └── dropdown data lainnya
```

Hindari membuat logic dropdown berulang pada setiap halaman.

---

## Visual behavior

Dropdown harus satu bahasa design dengan Command Center dan Portfolio:

* tinggi control konsisten;
* border konsisten;
* radius konsisten;
* font konsisten;
* hover state jelas;
* focus state jelas;
* selected state jelas;
* dropdown menu memiliki shadow ringan;
* selected option dapat diberi highlight;
* scrollbar ditampilkan jika option panjang.

Contoh referensi:

```text
┌─────────────────────────────────────┐
│ Pilih Gudang                    × ▴ │
├─────────────────────────────────────┤
│ ware                                │
├─────────────────────────────────────┤
│ Warehouse Depok - [WHJWB21060001]   │ ← selected/highlight
│ Warehouse Pontianak - [...]         │
│ Warehouse Mempawah - [...]          │
│ Warehouse Singkawang - [...]        │
│ Warehouse Sambas - [...]            │
│ Warehouse Aruk - [...]              │
│                               │█│   │
└─────────────────────────────────────┘
```

---

## Positioning dan overlay

Dropdown menu harus dapat tampil di atas card/table/component lain.

Pastikan tidak terjadi masalah:

```text
overflow: hidden
```

atau stacking context yang membuat dropdown terpotong.

Jika component/library mendukung portal/popover, gunakan pola existing yang paling aman.

Dropdown tidak boleh:

* terpotong card parent;
* berada di belakang section lain;
* membuat layout card berubah tinggi saat terbuka;
* menyebabkan horizontal page scroll.

---

## Keyboard accessibility

Minimal mendukung:

* `Tab` → fokus ke dropdown;
* `Enter` / `Space` → membuka dropdown;
* `Arrow Up/Down` → navigasi pilihan;
* `Enter` → memilih;
* `Esc` → menutup;
* typing → mencari apabila searchable.

Focus indicator harus terlihat.

---

## Empty dan loading state

Jika data sedang dimuat:

```text
Memuat data...
```

Jika pencarian tidak menemukan hasil:

```text
Data tidak ditemukan
```

Jika memang tidak ada pilihan karena permission atau source data:

tampilkan state yang jelas dan jangan membuat component tampak rusak.

---

## Performance

Untuk dataset yang besar:

* jangan melakukan render ribuan item tanpa pertimbangan;
* gunakan server-side search, debouncing, pagination, atau virtualization apabila architecture existing memang membutuhkannya;
* jangan mengubah API hanya demi visual jika jumlah data saat ini masih aman untuk client-side search.

Jika menggunakan search remote, hindari query per keystroke tanpa debounce.

---

## Scope standardisasi

Setelah component Portfolio stabil, component yang sama sebaiknya dapat digunakan pada modul lain yang mempunyai dropdown data seperti:

* Project;
* Mitra;
* User;
* Material;
* Unit;
* PoP;
* Pekerjaan Jasa;
* Warehouse;
* Request Material;
* Surat Jalan;
* Transit;

tanpa harus langsung merombak seluruh halaman dalam QC ini apabila scope terlalu besar.

Untuk QC ini, **minimal seluruh dropdown Portfolio wajib sudah menggunakan component/behavior yang konsisten**.

---

## Ketentuan implementasi

Jangan mengubah:

* authorization;
* permission;
* data scope;
* business logic filter;
* query semantics;
* export Excel;
* ownership;
* risk calculation;
* KPI calculation.

Dropdown hanya boleh menampilkan data yang memang authorized bagi user tersebut.

Jangan memperluas data access hanya supaya search dapat menemukan lebih banyak item.

Sebelum implementasi:

1. Inspect dropdown implementation saat ini.
2. Temukan penyebab `Status risiko` tidak dapat diklik.
3. Periksa console/runtime error.
4. Periksa DOM/CSS stacking.
5. Identifikasi select/autocomplete component existing.
6. Reuse library/component existing apabila tersedia.
7. Hindari menambahkan dependency baru jika tidak diperlukan.
8. Implementasikan reusable component.
9. Migrasikan dropdown Portfolio ke component tersebut.
10. Jalankan regression test.

## Dampak dan catatan

Bug pada **Status risiko** membuat sebagian fungsi filter Portfolio tidak dapat digunakan.

Ini berdampak langsung terhadap kemampuan User THC melakukan monitoring Project berdasarkan tingkat risiko.

Standardisasi searchable dropdown juga penting untuk scalability.

Saat jumlah entity bertambah, pola:

```text
scroll → cari manual → pilih
```

akan semakin tidak efisien.

Pola yang diinginkan:

```text
klik → ketik → pilih
```

Contoh:

```text
Warehouse

200 option
    ↓
ketik "dep"
    ↓
Warehouse Depok
    ↓
pilih
```

lebih cepat dan mengurangi risiko salah memilih item.

### Acceptance criteria

#### Bug Status risiko

* [ ] Dropdown Status risiko dapat diklik.
* [ ] Dropdown Status risiko dapat dibuka.
* [ ] Semua opsi authorized tampil.
* [ ] User dapat memilih opsi.
* [ ] Nilai terpilih tersimpan di filter state.
* [ ] `Terapkan filter` menggunakan nilai yang dipilih.
* [ ] `Reset filter` mengembalikan nilai default.
* [ ] Tidak ada JavaScript error ketika dropdown digunakan.

#### Searchable select

* [ ] Dropdown Project menggunakan component dropdown standar.
* [ ] Dropdown Mitra menggunakan component dropdown standar.
* [ ] Dropdown Periode menggunakan visual/component yang konsisten.
* [ ] Dropdown Status risiko menggunakan component dropdown standar.
* [ ] Dropdown dengan data banyak mendukung pencarian.
* [ ] Search bersifat case-insensitive.
* [ ] Search dapat menemukan nama entity.
* [ ] Search dapat menemukan code/identifier jika tersedia.
* [ ] Selected option terlihat jelas.
* [ ] Dropdown panjang memiliki scroll internal.
* [ ] Dropdown tidak terpotong oleh parent card.
* [ ] Dropdown tidak berada di belakang component lain.
* [ ] Dropdown tidak mengubah tinggi layout ketika dibuka.
* [ ] Dropdown mendukung keyboard navigation.
* [ ] Focus state terlihat.
* [ ] Empty state tersedia.
* [ ] Loading state tersedia jika relevan.
* [ ] Tidak terdapat horizontal page scroll.

#### Regression

* [ ] Filter Project tetap bekerja.
* [ ] Filter Mitra tetap bekerja.
* [ ] Filter Periode tetap bekerja.
* [ ] Filter Status risiko bekerja.
* [ ] Kombinasi beberapa filter tetap bekerja.
* [ ] Reset filter tetap bekerja.
* [ ] Unduh Excel tetap mengikuti scope/filter sesuai behavior existing.
* [ ] Permission dan authorized data tidak berubah.
* [ ] Tidak ada dummy/fake data.
* [ ] Tidak ada dependency UI baru kecuali memang diperlukan.

## Bukti QC

* `01-actual.png` — halaman Portfolio Cockpit dan kondisi filter saat pengujian.
* `02-searchable-dropdown-reference.png` — referensi expected behavior searchable dropdown dengan search field, option list, selected state, dan internal scrollbar.

> Tambahkan bukti berikutnya dengan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

## Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                                                                 |
| ------------ | ------ | ------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026-08-20` | `open` | Fatoni | Ditemukan dropdown Status risiko tidak dapat diklik. Diusulkan sekaligus standardisasi dropdown Portfolio menjadi reusable searchable select dengan interaction pattern yang konsisten. |
