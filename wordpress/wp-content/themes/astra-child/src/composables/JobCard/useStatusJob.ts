export function useStatusJob(status_pekerjaan: number): { label: string; color: string } {
	switch (status_pekerjaan) {
		case 2:
			return {
				label: 'Urgent',
				color: 'bg-red-600 text-white border border-red-700 shadow-sm text-md',
			};
		case 3:
			return {
				label: 'Pinned',
				color: 'bg-yellow-400 text-black border border-yellow-600 shadow-sm text-md',
			};
		default:
			return {
				label: '',
				color: '',
			};
	}
}
