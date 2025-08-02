export interface Job {
    id: number;
    title: string;
    jenis_pekerjaan_taxo?: string | string[];
    pendidikan_taxo?: string | string[];
    pengalaman?: number;
    gender_taxo?: string | string[];
    gaji_minimal?: number;
    gaji_maksimal?: number;
    umur_min?: number;
    umur_max?: number;
    lokasi_taxo?: string | string[];
    permalink?: string;
    slug?: string;
    
}

