<script setup>
import InlineRichText from '@/Components/Guides/InlineRichText.vue';

defineProps({
    sections: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="guides-prose">
        <section
            v-for="sec in sections"
            :id="sec.id"
            :key="sec.id"
            class="guides-section"
        >
            <component
                :is="sec.level === 3 ? 'h3' : 'h2'"
                class="guides-section-title"
            >
                <InlineRichText :text="sec.heading" />
            </component>

            <div
                v-for="(block, idx) in sec.blocks"
                :key="`${sec.id}-${idx}`"
                class="guides-block"
            >
                <p v-if="block.type === 'paragraph'" class="guides-p">
                    <InlineRichText :text="block.text" />
                </p>

                <div
                    v-else-if="block.type === 'callout'"
                    class="guides-callout"
                    :class="`guides-callout--${block.variant || 'info'}`"
                >
                    <p class="guides-callout-title"><InlineRichText :text="block.title" /></p>
                    <p class="guides-callout-text"><InlineRichText :text="block.text" /></p>
                </div>

                <ol v-else-if="block.type === 'list' && block.ordered" class="guides-list guides-list--ordered">
                    <li v-for="(item, i) in block.items" :key="i">
                        <InlineRichText :text="item" />
                    </li>
                </ol>

                <ul v-else-if="block.type === 'list'" class="guides-list">
                    <li v-for="(item, i) in block.items" :key="i">
                        <InlineRichText :text="item" />
                    </li>
                </ul>

                <div v-else-if="block.type === 'table'" class="guides-table-wrap">
                    <table class="guides-table">
                        <thead>
                            <tr>
                                <th v-for="(h, hi) in block.headers" :key="hi">
                                    <InlineRichText :text="h" />
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, ri) in block.rows" :key="ri">
                                <td v-for="(cell, ci) in row" :key="ci">
                                    <InlineRichText :text="cell" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <ul v-else-if="block.type === 'links'" class="guides-link-list">
                    <li v-for="(link, li) in block.items" :key="li">
                        <a
                            :href="link.url"
                            :target="link.external ? '_blank' : undefined"
                            :rel="link.external ? 'noopener noreferrer' : undefined"
                            class="guides-ext-link"
                        >
                            {{ link.label }}
                            <span v-if="link.external" aria-hidden="true">↗</span>
                        </a>
                    </li>
                </ul>

                <figure v-else-if="block.type === 'citation'" class="guides-citation">
                    <blockquote v-if="block.text" class="guides-citation-text">
                        <InlineRichText :text="block.text" />
                    </blockquote>
                    <figcaption v-if="block.source || block.url" class="guides-citation-source">
                        <span v-if="block.source"><InlineRichText :text="block.source" /></span>
                        <a
                            v-if="block.url"
                            :href="block.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="guides-ext-link"
                        >
                            Source ↗
                        </a>
                    </figcaption>
                </figure>
            </div>
        </section>
    </div>
</template>
