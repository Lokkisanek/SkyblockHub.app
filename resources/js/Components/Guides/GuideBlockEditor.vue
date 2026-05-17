<script setup>
import { computed } from 'vue';

const blockTypes = [
    { value: 'paragraph', label: 'Paragraph' },
    { value: 'callout', label: 'Callout' },
    { value: 'list', label: 'List' },
    { value: 'table', label: 'Table' },
    { value: 'links', label: 'Links' },
    { value: 'citation', label: 'Citation' },
];

const props = defineProps({
    sections: {
        type: Array,
        default: () => [],
    },
    usefulLinks: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:sections', 'update:usefulLinks']);

const sectionBlocks = computed(() => props.sections ?? []);
const links = computed(() => props.usefulLinks ?? []);

function clone(value) {
    return JSON.parse(JSON.stringify(value));
}

function slugify(value) {
    return String(value || '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function updateSections(next) {
    emit('update:sections', next);
}

function updateLinks(next) {
    emit('update:usefulLinks', next);
}

function updateSection(index, key, value) {
    const next = clone(sectionBlocks.value);
    next[index][key] = value;
    if (key === 'heading' && !next[index].id) {
        next[index].id = slugify(value);
    }
    updateSections(next);
}

function addSection() {
    updateSections([
        ...clone(sectionBlocks.value),
        {
            id: `section-${sectionBlocks.value.length + 1}`,
            heading: 'New section',
            level: 2,
            blocks: [{ type: 'paragraph', text: '' }],
        },
    ]);
}

function removeSection(index) {
    updateSections(clone(sectionBlocks.value).filter((_, i) => i !== index));
}

function moveSection(index, direction) {
    const next = clone(sectionBlocks.value);
    const target = index + direction;
    if (target < 0 || target >= next.length) return;
    [next[index], next[target]] = [next[target], next[index]];
    updateSections(next);
}

function addBlock(sectionIndex, type = 'paragraph', insertAfter = null) {
    const next = clone(sectionBlocks.value);
    const block = defaultBlock(type);

    if (Number.isInteger(insertAfter)) {
        next[sectionIndex].blocks.splice(insertAfter + 1, 0, block);
    } else {
        next[sectionIndex].blocks.push(block);
    }

    updateSections(next);
}

function updateBlock(sectionIndex, blockIndex, key, value) {
    const next = clone(sectionBlocks.value);
    next[sectionIndex].blocks[blockIndex][key] = value;
    updateSections(next);
}

function changeBlockType(sectionIndex, blockIndex, type) {
    const next = clone(sectionBlocks.value);
    next[sectionIndex].blocks[blockIndex] = defaultBlock(type);
    updateSections(next);
}

function removeBlock(sectionIndex, blockIndex) {
    const next = clone(sectionBlocks.value);
    next[sectionIndex].blocks = next[sectionIndex].blocks.filter((_, i) => i !== blockIndex);
    updateSections(next);
}

function moveBlock(sectionIndex, blockIndex, direction) {
    const next = clone(sectionBlocks.value);
    const blocks = next[sectionIndex].blocks;
    const target = blockIndex + direction;
    if (target < 0 || target >= blocks.length) return;
    [blocks[blockIndex], blocks[target]] = [blocks[target], blocks[blockIndex]];
    updateSections(next);
}

function defaultBlock(type) {
    if (type === 'callout') return { type, title: '', text: '', variant: 'info' };
    if (type === 'list') return { type, items: [''], ordered: false };
    if (type === 'table') return { type, headers: ['Column 1', 'Column 2'], rows: [['', '']] };
    if (type === 'links') return { type, items: [{ label: '', url: '', external: true }] };
    if (type === 'citation') return { type, text: '', source: '', url: '' };
    return { type: 'paragraph', text: '' };
}

function blockLabel(type) {
    return blockTypes.find((blockType) => blockType.value === type)?.label ?? 'Block';
}

function textareaRows(value, min = 2) {
    return Math.max(min, String(value || '').split('\n').length + 1);
}

function updateListItem(sectionIndex, blockIndex, itemIndex, value) {
    const next = clone(sectionBlocks.value);
    next[sectionIndex].blocks[blockIndex].items[itemIndex] = value;
    updateSections(next);
}

function addListItem(sectionIndex, blockIndex) {
    const next = clone(sectionBlocks.value);
    next[sectionIndex].blocks[blockIndex].items.push('');
    updateSections(next);
}

function removeListItem(sectionIndex, blockIndex, itemIndex) {
    const next = clone(sectionBlocks.value);
    next[sectionIndex].blocks[blockIndex].items = next[sectionIndex].blocks[blockIndex].items.filter((_, i) => i !== itemIndex);
    updateSections(next);
}

function updateTableHeader(sectionIndex, blockIndex, headerIndex, value) {
    const next = clone(sectionBlocks.value);
    next[sectionIndex].blocks[blockIndex].headers[headerIndex] = value;
    updateSections(next);
}

function updateTableCell(sectionIndex, blockIndex, rowIndex, cellIndex, value) {
    const next = clone(sectionBlocks.value);
    next[sectionIndex].blocks[blockIndex].rows[rowIndex][cellIndex] = value;
    updateSections(next);
}

function addTableRow(sectionIndex, blockIndex) {
    const next = clone(sectionBlocks.value);
    const width = next[sectionIndex].blocks[blockIndex].headers.length || 1;
    next[sectionIndex].blocks[blockIndex].rows.push(Array(width).fill(''));
    updateSections(next);
}

function removeTableRow(sectionIndex, blockIndex, rowIndex) {
    const next = clone(sectionBlocks.value);
    next[sectionIndex].blocks[blockIndex].rows = next[sectionIndex].blocks[blockIndex].rows.filter((_, i) => i !== rowIndex);
    updateSections(next);
}

function addTableColumn(sectionIndex, blockIndex) {
    const next = clone(sectionBlocks.value);
    const block = next[sectionIndex].blocks[blockIndex];
    block.headers.push(`Column ${block.headers.length + 1}`);
    block.rows = block.rows.map((row) => [...row, '']);
    updateSections(next);
}

function removeTableColumn(sectionIndex, blockIndex, columnIndex) {
    const next = clone(sectionBlocks.value);
    const block = next[sectionIndex].blocks[blockIndex];
    block.headers = block.headers.filter((_, i) => i !== columnIndex);
    block.rows = block.rows.map((row) => row.filter((_, i) => i !== columnIndex));
    updateSections(next);
}

function updateBlockLink(sectionIndex, blockIndex, linkIndex, key, value) {
    const next = clone(sectionBlocks.value);
    next[sectionIndex].blocks[blockIndex].items[linkIndex][key] = value;
    updateSections(next);
}

function addBlockLink(sectionIndex, blockIndex) {
    const next = clone(sectionBlocks.value);
    next[sectionIndex].blocks[blockIndex].items.push({ label: '', url: '', external: true });
    updateSections(next);
}

function removeBlockLink(sectionIndex, blockIndex, linkIndex) {
    const next = clone(sectionBlocks.value);
    next[sectionIndex].blocks[blockIndex].items = next[sectionIndex].blocks[blockIndex].items.filter((_, i) => i !== linkIndex);
    updateSections(next);
}

function addUsefulLink() {
    updateLinks([...clone(links.value), { label: '', url: '', external: true }]);
}

function updateUsefulLink(index, key, value) {
    const next = clone(links.value);
    next[index][key] = value;
    updateLinks(next);
}

function removeUsefulLink(index) {
    updateLinks(clone(links.value).filter((_, i) => i !== index));
}
</script>

<template>
    <div class="guide-editor">
        <div class="guide-editor-panel">
            <div class="guide-editor-head guide-editor-sticky-head">
                <button type="button" class="guides-action-btn guides-action-btn--primary" @click="addSection">Add section</button>
            </div>

            <div class="guide-editor-canvas guides-prose">
                <section v-for="(section, sectionIndex) in sectionBlocks" :id="section.id" :key="`${section.id}-${sectionIndex}`" class="guides-section guide-editable-section">
                    <div class="guide-editable-section-toolbar">
                        <label class="guide-editor-anchor">
                            Anchor
                            <input
                                :value="section.id"
                                class="guide-editor-ghost-input"
                                type="text"
                                @input="updateSection(sectionIndex, 'id', $event.target.value)"
                            >
                        </label>
                        <div class="guide-editor-actions">
                            <button type="button" class="guide-editor-mini" @click="moveSection(sectionIndex, -1)">Up</button>
                            <button type="button" class="guide-editor-mini" @click="moveSection(sectionIndex, 1)">Down</button>
                            <button type="button" class="guide-editor-mini guide-editor-danger" @click="removeSection(sectionIndex)">Remove section</button>
                        </div>
                    </div>

                    <input
                        :value="section.heading"
                        class="guides-section-title guide-editable-heading"
                        type="text"
                        placeholder="Section heading"
                        @input="updateSection(sectionIndex, 'heading', $event.target.value)"
                    >

                    <div v-for="(block, blockIndex) in section.blocks" :key="`${block.type}-${blockIndex}`" class="guides-block guide-editable-block">
                        <div class="guide-editable-block-toolbar">
                            <span class="guide-editor-block-pill">{{ blockLabel(block.type) }}</span>
                            <select
                                :value="block.type"
                                class="guide-editor-mini-select"
                                aria-label="Block type"
                                @change="changeBlockType(sectionIndex, blockIndex, $event.target.value)"
                            >
                                <option v-for="type in blockTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                            </select>
                            <button type="button" class="guide-editor-mini" @click="moveBlock(sectionIndex, blockIndex, -1)">Up</button>
                            <button type="button" class="guide-editor-mini" @click="moveBlock(sectionIndex, blockIndex, 1)">Down</button>
                            <button type="button" class="guide-editor-mini guide-editor-danger" @click="removeBlock(sectionIndex, blockIndex)">Remove</button>
                        </div>

                        <div v-if="block.type === 'paragraph'" class="guide-editable-paragraph">
                            <textarea
                                :value="block.text"
                                class="guides-p guide-editable-textarea guide-editable-textarea--paragraph"
                                :rows="textareaRows(block.text, 2)"
                                placeholder="Write paragraph text... Inline links use [text](https://...)."
                                @input="updateBlock(sectionIndex, blockIndex, 'text', $event.target.value)"
                            />
                        </div>

                        <div v-else-if="block.type === 'callout'" class="guides-callout guide-editable-callout" :class="`guides-callout--${block.variant || 'info'}`">
                            <div class="guide-editable-callout-head">
                                <input
                                    :value="block.title"
                                    class="guides-callout-title guide-editable-input guide-editable-input--strong"
                                    type="text"
                                    placeholder="Callout title"
                                    @input="updateBlock(sectionIndex, blockIndex, 'title', $event.target.value)"
                                >
                                <select
                                    :value="block.variant || 'info'"
                                    class="guide-editor-mini-select"
                                    @change="updateBlock(sectionIndex, blockIndex, 'variant', $event.target.value)"
                                >
                                    <option value="info">Info</option>
                                    <option value="tip">Tip</option>
                                    <option value="warning">Warning</option>
                                </select>
                            </div>
                            <textarea
                                :value="block.text"
                                class="guides-callout-text guide-editable-textarea guide-editable-textarea--muted"
                                :rows="textareaRows(block.text, 2)"
                                placeholder="Callout text..."
                                @input="updateBlock(sectionIndex, blockIndex, 'text', $event.target.value)"
                            />
                        </div>

                        <div v-else-if="block.type === 'list'" class="guide-editable-list-wrap">
                            <label class="guide-editor-check guide-editor-check--inline">
                                <input
                                    :checked="block.ordered"
                                    type="checkbox"
                                    @change="updateBlock(sectionIndex, blockIndex, 'ordered', $event.target.checked)"
                                >
                                Numbered list
                            </label>
                            <component :is="block.ordered ? 'ol' : 'ul'" class="guides-list guide-editable-list" :class="{ 'guides-list--ordered': block.ordered }">
                                <li v-for="(item, itemIndex) in block.items" :key="itemIndex">
                                    <input
                                        :value="item"
                                        class="guide-editable-input"
                                        type="text"
                                        placeholder="List item"
                                        @input="updateListItem(sectionIndex, blockIndex, itemIndex, $event.target.value)"
                                    >
                                    <button type="button" class="guide-editor-icon-btn" @click="removeListItem(sectionIndex, blockIndex, itemIndex)">Remove</button>
                                </li>
                            </component>
                            <button type="button" class="guide-editor-mini" @click="addListItem(sectionIndex, blockIndex)">Add item</button>
                        </div>

                        <div v-else-if="block.type === 'table'" class="guides-table-wrap guide-editable-table-wrap">
                            <table class="guides-table guide-editable-table">
                                <thead>
                                    <tr>
                                        <th v-for="(header, headerIndex) in block.headers" :key="headerIndex">
                                            <input
                                                :value="header"
                                                class="guide-editable-input guide-editable-input--strong"
                                                type="text"
                                                placeholder="Header"
                                                @input="updateTableHeader(sectionIndex, blockIndex, headerIndex, $event.target.value)"
                                            >
                                            <button type="button" class="guide-editor-icon-btn" @click="removeTableColumn(sectionIndex, blockIndex, headerIndex)">Remove column</button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, rowIndex) in block.rows" :key="rowIndex">
                                        <td v-for="(_, cellIndex) in block.headers" :key="cellIndex">
                                            <input
                                                :value="row[cellIndex] ?? ''"
                                                class="guide-editable-input"
                                                type="text"
                                                placeholder="Cell"
                                                @input="updateTableCell(sectionIndex, blockIndex, rowIndex, cellIndex, $event.target.value)"
                                            >
                                        </td>
                                        <td class="guide-editable-table-action">
                                            <button type="button" class="guide-editor-icon-btn" @click="removeTableRow(sectionIndex, blockIndex, rowIndex)">Remove row</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="guide-editor-actions">
                                <button type="button" class="guide-editor-mini" @click="addTableRow(sectionIndex, blockIndex)">Add row</button>
                                <button type="button" class="guide-editor-mini" @click="addTableColumn(sectionIndex, blockIndex)">Add column</button>
                            </div>
                        </div>

                        <div v-else-if="block.type === 'links'" class="guide-editable-links">
                            <ul class="guides-useful-list">
                                <li v-for="(link, linkIndex) in block.items" :key="linkIndex" class="guide-editable-link-item">
                                    <input
                                        :value="link.label"
                                        class="guides-ext-link guide-editable-input guide-editable-input--strong"
                                        type="text"
                                        placeholder="Link label"
                                        @input="updateBlockLink(sectionIndex, blockIndex, linkIndex, 'label', $event.target.value)"
                                    >
                                    <input
                                        :value="link.url"
                                        class="guide-editable-input guide-editable-input--url"
                                        type="url"
                                        placeholder="https://..."
                                        @input="updateBlockLink(sectionIndex, blockIndex, linkIndex, 'url', $event.target.value)"
                                    >
                                    <button type="button" class="guide-editor-icon-btn" @click="removeBlockLink(sectionIndex, blockIndex, linkIndex)">Remove</button>
                                </li>
                            </ul>
                            <button type="button" class="guide-editor-mini" @click="addBlockLink(sectionIndex, blockIndex)">Add link</button>
                        </div>

                        <figure v-else-if="block.type === 'citation'" class="guides-citation guide-editable-citation">
                            <textarea
                                :value="block.text"
                                class="guide-editable-textarea"
                                :rows="textareaRows(block.text, 3)"
                                placeholder="Quote, patch note excerpt, wiki note, or source summary..."
                                @input="updateBlock(sectionIndex, blockIndex, 'text', $event.target.value)"
                            />
                            <figcaption class="guides-citation-source guide-editable-citation-source">
                                <input
                                    :value="block.source"
                                    class="guide-editable-input guide-editable-input--compact"
                                    type="text"
                                    placeholder="Source name"
                                    @input="updateBlock(sectionIndex, blockIndex, 'source', $event.target.value)"
                                >
                                <input
                                    :value="block.url"
                                    class="guide-editable-input guide-editable-input--compact"
                                    type="url"
                                    placeholder="https://source-url.example"
                                    @input="updateBlock(sectionIndex, blockIndex, 'url', $event.target.value)"
                                >
                            </figcaption>
                        </figure>

                        <div class="guide-editor-insert-row">
                            <button v-for="type in blockTypes" :key="type.value" type="button" class="guide-editor-insert-btn" @click="addBlock(sectionIndex, type.value, blockIndex)">
                                + {{ type.label }}
                            </button>
                        </div>
                    </div>

                    <div v-if="section.blocks.length === 0" class="guide-editor-add-blocks guide-editor-add-blocks--section guide-editor-add-blocks--empty">
                        <button v-for="type in blockTypes" :key="type.value" type="button" class="guide-editor-mini" @click="addBlock(sectionIndex, type.value)">
                            + {{ type.label }}
                        </button>
                    </div>
                </section>

                <section class="guides-useful guide-editable-section guide-editable-section--references">
                    <div class="guide-editor-head">
                        <div>
                            <p class="guides-eyebrow">References</p>
                            <h2 class="guides-section-title">Useful links</h2>
                        </div>
                        <button type="button" class="guides-action-btn guides-action-btn--subtle" @click="addUsefulLink">Add link</button>
                    </div>
                    <ul class="guides-useful-list">
                        <li v-for="(link, index) in links" :key="index" class="guide-editable-link-item">
                            <input
                                :value="link.label"
                                class="guides-ext-link guide-editable-input guide-editable-input--strong"
                                type="text"
                                placeholder="Label"
                                @input="updateUsefulLink(index, 'label', $event.target.value)"
                            >
                            <input
                                :value="link.url"
                                class="guide-editable-input guide-editable-input--url"
                                type="url"
                                placeholder="https://..."
                                @input="updateUsefulLink(index, 'url', $event.target.value)"
                            >
                            <button type="button" class="guide-editor-icon-btn" @click="removeUsefulLink(index)">Remove</button>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </div>
</template>
