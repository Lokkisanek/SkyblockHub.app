import { progressionTopics } from './progression.js';
import { gameSystemTopics } from './gameSystems.js';
import { economyTopics } from './economy.js';
import { metaTopics } from './meta.js';

const allTopics = [
    ...progressionTopics,
    ...gameSystemTopics,
    ...economyTopics,
    ...metaTopics,
];

const bySlug = new Map(allTopics.map((t) => [t.slug, t]));

export function getGuideTopic(slug) {
    return bySlug.get(slug) ?? null;
}

export function getAllGuideTopics() {
    return allTopics;
}

/** Flat list for client-side search (title, description, section headings). */
export function buildGuideSearchIndex() {
    const entries = [];

    for (const topic of allTopics) {
        entries.push({
            slug: topic.slug,
            title: topic.title,
            description: topic.description,
            breadcrumb: topic.title,
            text: `${topic.title} ${topic.description}`,
        });

        for (const sec of topic.sections ?? []) {
            entries.push({
                slug: topic.slug,
                title: sec.heading,
                description: topic.title,
                breadcrumb: `${topic.title} › ${sec.heading}`,
                text: `${topic.title} ${sec.heading}`,
            });
        }
    }

    return entries;
}

export { allTopics };
