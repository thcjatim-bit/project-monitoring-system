import assert from 'node:assert/strict';
import test from 'node:test';

import { filterSearchableOptions } from '../../resources/js/searchable-select.js';

test('searchable select matches labels and identifiers case-insensitively', () => {
    const options = [
        { label: 'Warehouse Depok - WHJWB21060001', searchText: 'WHJWB21060001 Warehouse Depok' },
        { label: 'Warehouse Pontianak - WHKLB21060001', searchText: 'WHKLB21060001 Warehouse Pontianak' },
    ];

    assert.deepEqual(filterSearchableOptions(options, 'depok'), [options[0]]);
    assert.deepEqual(filterSearchableOptions(options, '21060001'), options);
    assert.deepEqual(filterSearchableOptions(options, 'not found'), []);
});
