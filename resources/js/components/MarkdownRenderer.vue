<!-- resources/js/components/MarkdownRenderer.vue -->
<template>
    <span class="markdown-content" v-html="renderedContent"></span>
</template>

<script setup>
import { computed } from 'vue'
import { marked } from 'marked'

const props = defineProps({
    content: {
        type: [String, Object],
        default: ''
    }
})

marked.setOptions({
    breaks: true,
    gfm: true,
})

const renderedContent = computed(() => {
    let text = props.content
    if (typeof text === 'object' && text !== null) {
        text = JSON.stringify(text)
    }
    if (!text || typeof text !== 'string') return ''
    return marked(text)
})
</script>

<style scoped>
.markdown-content {
    display: inline;
    margin: 0;
    padding: 0;
}

.markdown-content * {
    margin: 0 !important;
    padding: 0 !important;
}

.markdown-content p {
    display: inline !important;
    margin: 0 !important;
    padding: 0 !important;
}

.markdown-content br {
    display: none !important;
}

.markdown-content ul,
.markdown-content ol {
    display: inline !important;
    margin: 0 !important;
    padding: 0 !important;
    list-style: none !important;
}

.markdown-content li {
    display: inline !important;
    margin: 0 !important;
    padding: 0 !important;
}

.markdown-content li::before {
    content: "• ";
}

.markdown-content ol li::before {
    content: counter(list-item) ". ";
}

.markdown-content h1,
.markdown-content h2,
.markdown-content h3,
.markdown-content h4,
.markdown-content h5,
.markdown-content h6 {
    display: inline !important;
    font-weight: 600 !important;
    margin: 0 !important;
    padding: 0 !important;
}

.markdown-content blockquote {
    display: inline !important;
    margin: 0 !important;
    padding: 0 !important;
    border-left: 3px solid #d1d5db !important;
    padding-left: 0.5rem !important;
}

.markdown-content code {
    background-color: #f3f4f6 !important;
    padding: 0.1rem 0.3rem !important;
    border-radius: 0.25rem !important;
    font-size: 0.875rem !important;
    font-family: monospace !important;
}

.markdown-content pre {
    display: block !important;
    background-color: #1e293b !important;
    color: #e2e8f0 !important;
    padding: 0.3rem !important;
    border-radius: 0.5rem !important;
    overflow-x: auto !important;
    margin: 0 !important;
}

.markdown-content pre code {
    background-color: transparent !important;
    padding: 0 !important;
    color: inherit !important;
}

.markdown-content a {
    color: #2563eb !important;
    text-decoration: underline !important;
}

.markdown-content img {
    max-width: 100% !important;
    height: auto !important;
    border-radius: 0.5rem !important;
    display: block !important;
    margin: 0 !important;
}

.markdown-content hr {
    display: block !important;
    border: none !important;
    border-top: 2px solid #e5e7eb !important;
    margin: 0 !important;
    height: 0 !important;
}

.markdown-content table {
    border-collapse: collapse !important;
    width: 100% !important;
    margin: 0 !important;
}

.markdown-content th,
.markdown-content td {
    border: 1px solid #e5e7eb !important;
    padding: 0.2rem 0.5rem !important;
    text-align: left !important;
}

.markdown-content th {
    background-color: #f3f4f6 !important;
    font-weight: 600 !important;
}

.markdown-content strong {
    font-weight: 700 !important;
}

.markdown-content em {
    font-style: italic !important;
}

.markdown-content del {
    text-decoration: line-through !important;
    color: #9ca3af !important;
}
</style>