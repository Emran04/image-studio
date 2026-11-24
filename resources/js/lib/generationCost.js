const rateScale = 1_000_000_000n;
const displayScale = 10_000n;
const priceDivisor = rateScale * 1_000_000n * 100n / displayScale;
const tokenTypes = ['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'];

function hasValidRates(rates) {
    return tokenTypes.every((type) => typeof rates?.[type] === 'string'
        && /^[0-9]+(?:\.[0-9]{1,9})?$/.test(rates[type]));
}

function scaledRate(rate) {
    const [whole, fraction = ''] = rate.split('.');
    return BigInt(whole) * rateScale + BigInt(fraction.padEnd(9, '0'));
}

function formatUsd(markedUpCost) {
    const rounded = (markedUpCost + priceDivisor / 2n) / priceDivisor;
    return `$${rounded / displayScale}.${String(rounded % displayScale).padStart(4, '0')}`;
}

export function estimateGenerationCost(pricing, { prompt, size, quality, hasReference, referenceCount = Number(Boolean(hasReference)), markupPercent }) {
    const characterCount = Array.from(prompt.trim()).length;
    if (!pricing || characterCount === 0) {
        return null;
    }

    const { rates, tokens } = pricing;
    const reasoning = tokens.reasoning[quality];
    const outputTokens = tokens.output[quality]?.[size];
    if (!reasoning || outputTokens === undefined || !hasValidRates(rates)) {
        return null;
    }

    const typicalTextTokens = Math.ceil(characterCount / tokens.characters_per_text_token);
    const minTextTokens = Math.max(1, Math.floor(typicalTextTokens * (1 - tokens.text_token_variance)));
    const maxTextTokens = Math.ceil(typicalTextTokens * (1 + tokens.text_token_variance));

    const textRate = scaledRate(rates.input_text_tokens);
    const reasoningRate = scaledRate(rates.reasoning_tokens);
    const fixedCost = BigInt(outputTokens) * scaledRate(rates.output_tokens)
        + BigInt(referenceCount) * BigInt(tokens.per_reference_image) * scaledRate(rates.input_image_tokens);
    const markup = BigInt(100 + markupPercent);

    return {
        min: formatUsd((fixedCost + BigInt(minTextTokens) * textRate + BigInt(reasoning.min) * reasoningRate) * markup),
        max: formatUsd((fixedCost + BigInt(maxTextTokens) * textRate + BigInt(reasoning.max) * reasoningRate) * markup),
    };
}

export function actualGenerationCost({ status, usage, rates, markupPercent }) {
    if (status === 'failed') {
        return formatUsd(0n);
    }

    if (status !== 'succeeded' || !hasValidRates(rates)
        || !tokenTypes.every((type) => Number.isSafeInteger(usage?.[type]) && usage[type] >= 0)) {
        return null;
    }

    const cost = tokenTypes.reduce((total, type) => total + BigInt(usage[type]) * scaledRate(rates[type]), 0n);

    return formatUsd(cost * BigInt(100 + markupPercent));
}
