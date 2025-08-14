import { formatSalary, formatAge } from "@/services/Formatting";
import { type JobSummary, type JobContactRow } from "@/types";

export interface SummaryRow {
  icon: string
  label: string
  value: string
}
export interface ContactRow {
  type: string
  icon: string
  label: string
  value: string
  href: string
}

export function useSummaryJob(jobdata: JobSummary | null | undefined): SummaryRow[] {
  const rows: SummaryRow[] = [];
  const data: JobSummary = (jobdata ?? {}) as JobSummary;

  if (data['jenis_pekerjaan_taxo']) {
    rows.push({
      icon: 'fa-clock',
      label: 'Jenis Pekerjaan',
      value: Array.isArray(data['jenis_pekerjaan_taxo'])
        ? data['jenis_pekerjaan_taxo'].join(', ')
        : String(data['jenis_pekerjaan_taxo'] ?? ''),
    });
  }
  if (data['pendidikan_taxo']) {
    rows.push({
      icon: 'fa-graduation-cap',
      label: 'Pendidikan',
      value: Array.isArray(data['pendidikan_taxo'])
        ? data['pendidikan_taxo'].join(', ')
        : String(data['pendidikan_taxo'] ?? ''),
    });
  }
  if (data['pengalaman']) {
    rows.push({
      icon: 'fa-briefcase',
      label: 'Pengalaman',
      value: `Minimal ${data['pengalaman']} Tahun Pengalaman`,
    });
  }
  if (data['gender_taxo']) {
    rows.push({
      icon: 'fa-venus-mars',
      label: 'Gender',
      value: Array.isArray(data['gender_taxo'])
        ? data['gender_taxo'].join(', ')
        : String(data['gender_taxo'] ?? ''),
    });
  }
  const gaji_min = data['gaji_minimal'] ? Number(data['gaji_minimal']) : undefined;
  const gaji_max = data['gaji_maksimal'] ? Number(data['gaji_maksimal']) : undefined;
  const gaji_display = formatSalary(gaji_min, gaji_max);
  if (gaji_display) {
    rows.push({
      icon: 'fa-money-bill-wave',
      label: 'Gaji',
      value: gaji_display,
    });
  }
  const umur_min = data['umur_min'] ? Number(data['umur_min']) : undefined;
  const umur_max = data['umur_max'] ? Number(data['umur_max']) : undefined;
  const umur_display = formatAge(umur_min, umur_max);
  if (umur_display) {
    rows.push({
      icon: 'fa-birthday-cake',
      label: 'Usia',
      value: umur_display,
    });
  }
  if (data['lokasi_taxo']) {
    rows.push({
      icon: 'fa-map-marker-alt',
      label: 'Lokasi',
      value: Array.isArray(data['lokasi_taxo'])
        ? data['lokasi_taxo'].join(', ')
        : String(data['lokasi_taxo'] ?? ''),
    });
  }
  if (data['deadline']) {
    rows.push({
      icon: 'fa-calendar-alt',
      label: 'Deadline',
      value: data['deadline'],
    });
  }

  return rows;
}

export function useContactsJob(jobdata: JobContactRow): ContactRow[] {
  const contacts: ContactRow[] = [];

  (jobdata.email_kontak ?? []).forEach((email) => {
    if (email) {
      contacts.push({
        type: 'email',
        icon: 'fas fa-envelope',
        label: 'Email',
        value: email,
        href: `mailto:${email}`,
      });
    }
  });

  (jobdata.nomor_kontak ?? []).forEach((phone) => {
    if (phone) {
      contacts.push({
        type: 'phone',
        icon: 'fas fa-phone',
        label: 'Telepon',
        value: phone,
        href: `tel:${phone}`,
      });
    }
  });

  (jobdata.situs_kontak ?? []).forEach((site) => {
    if (site) {
      contacts.push({
        type: 'website',
        icon: 'fas fa-globe',
        label: 'Website',
        value: site.replace(/^https?:\/\//, ''),
        href: site,
      });
    }
  });

  return contacts;
}