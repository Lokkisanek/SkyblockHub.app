import { usePage } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

export function useSeo(pageTitle = null, pageDescription = null) {
    const page = usePage();
    const seo = computed(() => page.props.seo);
    
    const title = computed(() => pageTitle || seo.value?.title || 'SkyblockHub');
    const description = computed(() => pageDescription || seo.value?.description || '');
    const ogImage = computed(() => seo.value?.ogImage || '/img/logo-white.webp');
    const url = computed(() => window.location.href);
    
    // Update document title
    watch(() => title.value, (newTitle) => {
        document.title = newTitle;
    }, { immediate: true });
    
    // Update meta description
    watch(() => description.value, (newDesc) => {
        const metaDesc = document.querySelector('meta[name="description"]');
        if (metaDesc) {
            metaDesc.setAttribute('content', newDesc);
        }
    }, { immediate: true });
    
    // Update Open Graph tags
    watch(() => ({ title: title.value, description: description.value, image: ogImage.value }), (values) => {
        updateMetaTag('property', 'og:title', values.title);
        updateMetaTag('property', 'og:description', values.description);
        updateMetaTag('property', 'og:image', values.image);
        updateMetaTag('property', 'og:url', url.value);
        updateMetaTag('name', 'twitter:title', values.title);
        updateMetaTag('name', 'twitter:description', values.description);
        updateMetaTag('name', 'twitter:image', values.image);
        
        // Update canonical link
        const canonical = document.querySelector('link[rel="canonical"]');
        if (canonical) {
            canonical.href = url.value;
        }
    }, { immediate: true });
    
    return { title, description, ogImage, url };
}

function updateMetaTag(attrName, attrValue, content) {
    let meta = document.querySelector(`meta[${attrName}="${attrValue}"]`);
    if (!meta) {
        meta = document.createElement('meta');
        meta.setAttribute(attrName, attrValue);
        document.head.appendChild(meta);
    }
    meta.setAttribute('content', content);
}
