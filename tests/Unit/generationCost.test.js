import assert from 'node:assert/strict';
import test from 'node:test';
import { actualGenerationCost, estimateGenerationCost } from '../../resources/js/lib/generationCost.js';

const pricing = {
    rates: { input_text_tokens: '5.00', input_image_tokens: '10.00', output_tokens: '40.00', reasoning_tokens: '12.50' },
    tokens: {
        characters_per_text_token: 4,
        text_token_variance: 0.15,
        per_reference_image: 765,
        output: { medium: { '1024x1024': 1100, '1536x1024': 1650 } },
        reasoning: { medium: { min: 200, max: 2000 } },
    },
};
const square = { prompt: 'a'.repeat(400), size: '1024x1024', quality: 'medium', hasReference: false, markupPercent: 25 };

test('applies the markup to a range that includes text and reasoning variance', () => {
    assert.deepEqual(estimateGenerationCost(pricing, square), { min: '$0.0587', max: '$0.0870' });
});

test('includes the selected size and optional reference image and counts Unicode characters', () => {
    assert.deepEqual(estimateGenerationCost(pricing, {
        ...square,
        prompt: `  ${'🌙'.repeat(400)}  `,
        size: '1536x1024',
        hasReference: true,
    }), { min: '$0.0957', max: '$0.1240' });
});

test('returns no estimate for an empty prompt or unavailable pricing', () => {
    assert.equal(estimateGenerationCost(pricing, { ...square, prompt: '   ' }), null);
    assert.equal(estimateGenerationCost(null, square), null);
});

test('estimates a generation with multiple references at its saved rates', () => {
    const savedPricing = { ...pricing, rates: { ...pricing.rates, output_tokens: '41.00' } };
    assert.deepEqual(estimateGenerationCost(savedPricing, { ...square, referenceCount: 2 }), {
        min: '$0.0792', max: '$0.1075',
    });
});

test('returns no estimate for an unsupported quality or size', () => {
    assert.equal(estimateGenerationCost(pricing, { ...square, quality: 'unknown' }), null);
    assert.equal(estimateGenerationCost(pricing, { ...square, size: 'unknown' }), null);
});

test('calculates the actual cost from all four token counts and saved rates with markup', () => {
    assert.equal(actualGenerationCost({
        status: 'succeeded',
        usage: { input_text_tokens: 12, input_image_tokens: 765, output_tokens: 6450, reasoning_tokens: 3184, total_tokens: 10411 },
        rates: pricing.rates,
        markupPercent: 25,
    }), '$0.3819');
});

test('shows no actual cost when usage or rates are missing and zero for a failed generation', () => {
    assert.equal(actualGenerationCost({ status: 'queued', markupPercent: 25 }), null);
    assert.equal(actualGenerationCost({ status: 'succeeded', usage: { total_tokens: 1500 }, rates: pricing.rates, markupPercent: 25 }), null);
    assert.equal(actualGenerationCost({ status: 'succeeded', markupPercent: 25 }), null);
    assert.equal(actualGenerationCost({ status: 'failed', markupPercent: 25 }), '$0.0000');
});
