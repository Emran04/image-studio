export const metrics = [
    { key: 'spend', label: 'Credit spend', description: 'Calculated user price' },
    { key: 'providerCost', label: 'Provider cost', description: 'Reported by Pixelforge' },
    { key: 'profit', label: 'Profit', description: 'Credit spend minus provider cost' },
];

const usd = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 4,
    maximumFractionDigits: 9,
});

export function amount(row, key) {
    const missingSpend = key === 'spend' && row.generations > 0 && row.generations === row.missingPricing;

    return row[key] === null || missingSpend ? '—' : usd.format(row[key]);
}
