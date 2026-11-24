export const metrics = [
    { key: 'spend', label: 'Credit spend', description: 'Calculated user price' },
    { key: 'providerCost', label: 'Provider cost', description: 'Pixelforge usage × saved rates' },
    { key: 'profit', label: 'Profit', description: 'Credit spend minus provider cost' },
];

const usd = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 4,
    maximumFractionDigits: 4,
});

export function amount(row, key) {
    return row.generations > 0 && row.generations === row.missingPricing ? '—' : usd.format(row[key]);
}
