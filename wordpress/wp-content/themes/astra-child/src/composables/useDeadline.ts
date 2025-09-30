export function useDeadline(deadline: string | null | undefined): { text: string; style: string } {
	if (!deadline) {
		return { text: '', style: '' };
	}
	let normalized = deadline;
	if (typeof normalized === 'string' && /^\d{2}-\d{2}-\d{4}$/.test(normalized)) {
		const [day, month, year] = normalized.split('-');
		normalized = `${year}-${month}-${day}`;
	}
	const deadlineDate = new Date(normalized);
	const now = new Date();
	deadlineDate.setHours(0, 0, 0, 0);
	now.setHours(0, 0, 0, 0);
	const msPerDay = 1000 * 60 * 60 * 24;
	const days_left = Math.floor((deadlineDate.getTime() - now.getTime()) / msPerDay);
	let text = '';
	let style = '';
	if (days_left > 1) {
		text = `Sisa ${days_left} hari`;
		style = 'bg-blue-600 text-white border border-blue-800';
	} else if (days_left === 1) {
		text = 'Sisa 1 hari';
		style = 'bg-yellow-400 text-black border border-yellow-600';
	} else if (days_left === 0) {
		text = 'Hari terakhir';
		style = 'bg-red-600 text-white border border-red-800';
	} else if (days_left === -1) {
		text = 'Berakhir kemarin';
		style = 'bg-gray-500 text-white border border-gray-700';
	} else if (days_left < -1) {
		text = `Berakhir ${Math.abs(days_left)} hari lalu`;
		style = 'bg-gray-400 text-black border border-gray-700';
	} else {
		text = 'Berakhir hari ini';
		style = 'bg-red-600 text-white border border-red-800';
	}
	return { text, style };
}
