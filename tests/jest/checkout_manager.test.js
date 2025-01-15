import {
    getDatasetValue,
    getChangedInputs
} from '../../amd/src/checkout_manager';

describe('getDatasetValue', () => {
    test('should return dataset value if key exists', () => {
        const element = document.createElement('div');
        element.dataset.key = 'value';
        expect(getDatasetValue(element, 'key')).toBe('value');
    });

    test('should return empty string if key does not exist', () => {
        const element = document.createElement('div');
        expect(getDatasetValue(element, 'missingKey')).toBe('');
    });
});

describe('getChangedInputs', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <input data-shopping-cart-process-data="true" name="input1" value="value1" />
            <input data-shopping-cart-process-data="true" type="checkbox" checked />
            <input data-shopping-cart-process-data="false" name="input3" type="radio" value="value3" checked />
            <input data-shopping-cart-process-data="true" name="input4" type="radio" value="value4" checked/>
            <input name="input5" type="radio" value="value5" />
        `;
    });

    test('should gather input values correctly', () => {
        const inputs = getChangedInputs();
        expect(inputs).toEqual([
            { name: 'input1', value: 'value1' },
            { name: 'unnamed', value: true },
            { name: 'input4', value: 'value4' },
        ]);
    });

    test('should return an empty array if no matching elements are found', () => {
        document.body.innerHTML = '';
        expect(getChangedInputs()).toEqual([]);
    });
});