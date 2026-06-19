<script setup>
import { ref, computed, nextTick, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { useStream } from '@laravel/stream-vue'
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'

const props = defineProps({
    conversations: {
        type: Array,
        default: () => []
    }
})

const conversations = ref(props.conversations ?? [])
const activeId = ref(conversations.value[0]?.id ?? null)
const message = ref('')
const loading = ref(false)
const showDeleteModal = ref(false)
const conversationToDelete = ref(null)
const showCommands = ref(false)
const showModelSelector = ref(false)

// Variables pour le streaming
const isStreaming = ref(false)
const streamedContent = ref('')
const streamedReasoning = ref('')
const fullStreamedResponse = ref('')
const isConnecting = ref(false)

// Theme sombre/clair
const darkMode = ref(localStorage.getItem('theme') === 'dark' || false)

// Stats session
const sessionStats = ref({
    messagesSent: 0,
    totalTokens: 0,
    estimatedCost: 0,
    sessionStart: new Date(),
    wordsGenerated: 0
})

// Export
const showExportModal = ref(false)
const exportFormat = ref('md')
const exportOptions = ref({
    includeMetadata: true,
    includeReasoning: false,
    includeStats: true
})

// Suggestions rapides
const quickSuggestions = ref([
    'Explique-moi ce concept',
    'Donne-moi un exemple',
    'Resume ce texte',
    'Traduis en francais'
])

// Mode thinking
const showThinking = ref(true)

// Keyboard shortcuts
const shortcuts = ref({
    newConversation: 'Ctrl+N',
    toggleDark: 'Ctrl+Shift+D'
})

// ============================================================
// MODELES
// ============================================================

const models = [
    { 
        id: 'openai/gpt-4o-mini', 
        name: 'GPT-4o Mini', 
        provider: 'OpenAI',
        description: 'Rapide, efficace et pas cher',
        pricePer1k: 0.00015
    },
    { 
        id: 'openai/gpt-4o', 
        name: 'GPT-4o', 
        provider: 'OpenAI',
        description: 'Plus puissant, bon pour les taches complexes',
        pricePer1k: 0.005
    },
    { 
        id: 'openai/gpt-3.5-turbo', 
        name: 'GPT-3.5 Turbo', 
        provider: 'OpenAI',
        description: 'Le moins cher, rapide pour les reponses simples',
        pricePer1k: 0.0005
    },
    { 
        id: 'anthropic/claude-3-5-sonnet-20241022', 
        name: 'Claude 3.5 Sonnet', 
        provider: 'Anthropic',
        description: 'Excellent pour le raisonnement',
        pricePer1k: 0.003
    },
    { 
        id: 'google/gemini-2.0-flash-exp', 
        name: 'Gemini 2.0 Flash', 
        provider: 'Google',
        description: 'Ultra rapide, bonne qualite',
        pricePer1k: 0.001
    },
    { 
        id: 'meta-llama/llama-3.3-70b-instruct', 
        name: 'Llama 3.3 70B', 
        provider: 'Meta',
        description: 'Open source, tres performant',
        pricePer1k: 0.0008
    }
]

const selectedModel = ref('openai/gpt-4o-mini')

// Hook de streaming
const { data, isFetching, isStreaming: streamActive, send, cancel } = useStream(
    '/chat/stream',
    {
        onData: () => {
            const content = extractContent(data.value || '')
            const reasoning = extractReasoning(data.value || '')
            streamedContent.value = content
            streamedReasoning.value = reasoning
            fullStreamedResponse.value = data.value || ''
            isConnecting.value = false
            nextTick(() => scrollToBottom())
        },
        onFinish: () => {
            if (activeConversation.value && fullStreamedResponse.value) {
                const content = extractContent(fullStreamedResponse.value)
                const reasoning = extractReasoning(fullStreamedResponse.value)
                
                const conv = activeConversation.value
                if (conv) {
                    if (!Array.isArray(conv.messages)) {
                        conv.messages = []
                    }
                    
                    const estimatedTokens = Math.ceil(content.length / 4)
                    const modelCost = models.find(m => m.id === selectedModel.value)?.pricePer1k || 0
                    
                    conv.messages.push({
                        role: 'assistant',
                        content: content,
                        reasoning: reasoning || null,
                        model: selectedModel.value,
                        tokens: estimatedTokens,
                        cost: estimatedTokens * modelCost / 1000,
                        timestamp: new Date().toISOString()
                    })
                    
                    sessionStats.value.totalTokens += estimatedTokens
                    sessionStats.value.estimatedCost += estimatedTokens * modelCost / 1000
                    sessionStats.value.messagesSent++
                    sessionStats.value.wordsGenerated += content.split(/\s+/).length
                }
            }
            
            isStreaming.value = false
            isConnecting.value = false
            streamedContent.value = ''
            streamedReasoning.value = ''
            fullStreamedResponse.value = ''
            message.value = ''
            loading.value = false
        },
        onError: (err) => {
            console.error('Erreur streaming:', err)
            isStreaming.value = false
            isConnecting.value = false
            loading.value = false
            streamedContent.value = ''
            streamedReasoning.value = ''
            
            const conv = activeConversation.value
            if (conv) {
                conv.messages.push({
                    role: 'assistant',
                    content: 'Erreur: ' + err.message,
                    model: null,
                    timestamp: new Date().toISOString()
                })
            }
        }
    }
)

// ============================================================
// FONCTIONS EXISTANTES
// ============================================================

const extractContent = (text) => {
    if (!text) return ''
    return text.replace(/\[REASONING\][\s\S]*?\[\/REASONING\]/g, '').trim()
}

const extractReasoning = (text) => {
    if (!text) return ''
    const matches = text.match(/\[REASONING\]([\s\S]*?)\[\/REASONING\]/g)
    if (!matches) return ''
    return matches.map((m) => m.replace(/\[REASONING\]/g, '').replace(/\[\/REASONING\]/g, '')).join('')
}

const hasReasoning = computed(() => {
    return streamedReasoning.value && streamedReasoning.value.length > 0
})

const cancelStream = () => {
    if (streamActive.value) {
        cancel()
        isStreaming.value = false
        isConnecting.value = false
        loading.value = false
        
        const conv = activeConversation.value
        if (conv) {
            conv.messages.push({
                role: 'assistant',
                content: 'Reponse annulee.',
                model: null,
                timestamp: new Date().toISOString()
            })
        }
    }
}

// ============================================================
// SAUVEGARDE MODELE
// ============================================================

const savePreferredModel = async (modelId) => {
    try {
        await fetch('/user/ai-profile', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({ preferred_model: modelId })
        })
    } catch (err) {
        console.error('Erreur sauvegarde modele:', err)
    }
}

const loadPreferredModel = async () => {
    try {
        const response = await fetch('/user/ai-profile')
        const data = await response.json()
        if (data.preferred_model && models.some(m => m.id === data.preferred_model)) {
            selectedModel.value = data.preferred_model
        }
    } catch (err) {
        console.error('Erreur chargement modele:', err)
    }
}

const selectModel = async (modelId) => {
    selectedModel.value = modelId
    showModelSelector.value = false
    await savePreferredModel(modelId)
}

const toggleModelSelector = () => {
    showModelSelector.value = !showModelSelector.value
}

const getSelectedModelInfo = () => {
    return models.find(m => m.id === selectedModel.value) || models[0]
}

const getMessageModelName = (message) => {
    if (message.model) {
        const model = models.find(m => m.id === message.model)
        return model ? model.name : message.model.split('/').pop()
    }
    return null
}

const activeConversation = computed(() => {
    return conversations.value.find(c => c?.id === activeId.value) || null
})

const messages = computed(() => {
    return activeConversation.value?.messages || []
})

// ============================================================
// THEME
// ============================================================

const toggleDarkMode = () => {
    darkMode.value = !darkMode.value
    localStorage.setItem('theme', darkMode.value ? 'dark' : 'light')
    if (darkMode.value) {
        document.documentElement.classList.add('dark')
    } else {
        document.documentElement.classList.remove('dark')
    }
}

// ============================================================
// EXPORT
// ============================================================

const exportConversation = () => {
    const conv = activeConversation.value
    if (!conv) return
    
    let content = ''
    
    if (exportOptions.value.includeMetadata) {
        content += `# ${conv.title}\n\n`
        content += `Exporte le: ${new Date().toLocaleString()}\n`
        content += `Modele: ${getSelectedModelInfo().name}\n`
        content += `Messages: ${conv.messages.length}\n\n---\n\n`
    }
    
    conv.messages.forEach((m, i) => {
        const role = m.role === 'user' ? 'Utilisateur' : 'Assistant'
        content += `## ${role} (${i + 1})\n\n`
        content += m.content + '\n\n'
        if (exportOptions.value.includeReasoning && m.reasoning) {
            content += `**Reflexion:**\n${m.reasoning}\n\n`
        }
        if (exportOptions.value.includeStats && m.tokens) {
            content += `*${m.tokens} tokens, $${(m.cost || 0).toFixed(6)}*\n\n`
        }
        content += '---\n\n'
    })
    
    if (exportOptions.value.includeStats) {
        content += `\n## Statistiques\n\n`
        content += `- Messages: ${sessionStats.value.messagesSent}\n`
        content += `- Tokens: ${sessionStats.value.totalTokens}\n`
        content += `- Cout estime: $${sessionStats.value.estimatedCost.toFixed(6)}\n`
    }
    
    const blob = new Blob([content], { type: 'text/plain' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${conv.title}.${exportFormat.value}`
    a.click()
    URL.revokeObjectURL(url)
    showExportModal.value = false
}

// ============================================================
// SUGGESTIONS
// ============================================================

const useSuggestion = (suggestion) => {
    message.value = suggestion
    nextTick(() => {
        const input = document.querySelector('input[type="text"]')
        if (input) input.focus()
    })
}

// ============================================================
// THINKING
// ============================================================

const toggleThinking = () => {
    showThinking.value = !showThinking.value
}

// ============================================================
// FONCTIONS EXISTANTES
// ============================================================

const goToAiSettings = () => {
    router.visit('/settings/ai')
}

const toggleCommands = () => {
    showCommands.value = !showCommands.value
}

const newConversation = async () => {
    try {
        const res = await fetch('/chat/conversation', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            }
        })

        let data = await res.json()
        if (!res.ok) throw new Error(data?.message || "Erreur creation conversation")
        if (!data?.id) throw new Error("Conversation invalide")

        const newConv = {
            id: data.id,
            title: data.title ?? 'Nouvelle conversation',
            messages: [],
            createdAt: new Date()
        }

        conversations.value.unshift(newConv)
        activeId.value = newConv.id
        sessionStats.value.messagesSent = 0
        sessionStats.value.totalTokens = 0
        sessionStats.value.estimatedCost = 0
        sessionStats.value.sessionStart = new Date()

    } catch (err) {
        console.error('Erreur creation conversation:', err.message)
    }
}

const switchConversation = (id) => {
    activeId.value = id
    nextTick(() => scrollToBottom())
}

const confirmDelete = (id, event) => {
    event.stopPropagation()
    conversationToDelete.value = id
    showDeleteModal.value = true
}

const deleteConversation = async () => {
    if (!conversationToDelete.value) return

    try {
        const res = await fetch(`/chat/conversation/${conversationToDelete.value}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            }
        })

        let data = await res.json()
        if (!res.ok) throw new Error(data?.message || "Erreur suppression")

        conversations.value = conversations.value.filter(c => c?.id !== conversationToDelete.value)
        if (activeId.value === conversationToDelete.value) {
            activeId.value = conversations.value[0]?.id ?? null
        }

    } catch (err) {
        console.error('Erreur suppression:', err.message)
        alert('Erreur: ' + err.message)
    }

    showDeleteModal.value = false
    conversationToDelete.value = null
}

const cancelDelete = () => {
    showDeleteModal.value = false
    conversationToDelete.value = null
}

const scrollToBottom = () => {
    const el = document.getElementById('chat-box')
    if (el) {
        el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' })
    }
}

const isCommand = (text) => text.startsWith('/')
const getCommandName = (text) => text.split(' ')[0]

// ============================================================
// SEND MESSAGE
// ============================================================

const sendMessage = async () => {
    if (!message.value.trim() || loading.value || !activeId.value) return

    const text = message.value
    message.value = ''
    
    const conv = activeConversation.value
    if (!conv) return

    if (!Array.isArray(conv.messages)) {
        conv.messages = []
    }

    conv.messages.push({
        role: 'user',
        content: text,
        model: null,
        timestamp: new Date().toISOString()
    })

    // Afficher immediatement "L'IA reflechit..."
    isConnecting.value = true
    loading.value = true

    try {
        const response = await fetch('/chat/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                message: text,
                conversation_id: activeId.value,
                model: selectedModel.value
            })
        })
        
        const data = await response.json()
        if (data.conversation_title && conv.title === 'Nouvelle conversation') {
            conv.title = data.conversation_title
        }
    } catch (err) {
        console.error('Erreur sauvegarde message utilisateur:', err)
    }

    streamedContent.value = ''
    streamedReasoning.value = ''
    fullStreamedResponse.value = ''
    isStreaming.value = true

    try {
        await send({
            message: text,
            model: selectedModel.value,
            temperature: 0.7,
            reasoning_effort: null
        })
    } catch (err) {
        console.error('Erreur:', err)
        isStreaming.value = false
        isConnecting.value = false
        loading.value = false
    }
}

// ============================================================
// KEYBOARD SHORTCUTS
// ============================================================

const handleKeydown = (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key === 'n') {
        event.preventDefault()
        newConversation()
    }
    if ((event.ctrlKey || event.metaKey) && event.shiftKey && event.key === 'D') {
        event.preventDefault()
        toggleDarkMode()
    }
}

// ============================================================
// COMPUTED
// ============================================================

const formattedStats = computed(() => {
    const duration = Math.floor((Date.now() - new Date(sessionStats.value.sessionStart).getTime()) / 1000)
    const hours = Math.floor(duration / 3600)
    const minutes = Math.floor((duration % 3600) / 60)
    const seconds = duration % 60
    
    return {
        duration: `${hours}h ${minutes}m ${seconds}s`,
        cost: '$' + sessionStats.value.estimatedCost.toFixed(6),
        tokens: sessionStats.value.totalTokens.toLocaleString()
    }
})

const hasMessages = computed(() => messages.value && messages.value.length > 0)

// ============================================================
// ON MOUNT
// ============================================================

onMounted(() => {
    loadPreferredModel()
    // Appliquer le theme au chargement
    if (darkMode.value) {
        document.documentElement.classList.add('dark')
    } else {
        document.documentElement.classList.remove('dark')
    }
    document.addEventListener('keydown', handleKeydown)
})
</script>

<template>
<div :class="['flex h-screen', darkMode ? 'dark' : '']">
    <div class="flex h-screen w-full bg-gray-50 dark:bg-gray-900">

    <!-- ============================================================
    SIDEBAR
    ============================================================ -->
    <aside class="w-80 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 flex flex-col flex-shrink-0">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between mb-3">
                <span class="text-lg font-bold text-gray-900 dark:text-white">DesignMentor</span>
                <button
                    @click="toggleDarkMode"
                    class="text-xs text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 flex items-center gap-1"
                >
                    <span>Theme</span>
                    <span>{{ darkMode ? '🌙' : '☀️' }}</span>
                </button>
            </div>
            
            <button
                @click="newConversation"
                class="w-full bg-gray-900 dark:bg-gray-700 text-white px-4 py-2.5 rounded-lg hover:bg-gray-800 dark:hover:bg-gray-600 transition-all duration-200 text-sm font-medium"
            >
                + Nouvelle conversation
            </button>
        </div>

        <!-- Selection du modele -->
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <div class="relative">
                <button
                    @click="toggleModelSelector"
                    class="w-full flex items-center justify-between px-3 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-all duration-200 text-sm"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="text-left truncate">
                            <div class="text-gray-700 dark:text-gray-200 font-medium truncate text-sm">{{ getSelectedModelInfo().name }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ getSelectedModelInfo().provider }}</div>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-gray-500 transition-transform flex-shrink-0" :class="showModelSelector ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div v-if="showModelSelector" class="absolute top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-20 max-h-60 overflow-y-auto">
                    <div class="py-1">
                        <button
                            v-for="model in models"
                            :key="model.id"
                            @click="selectModel(model.id)"
                            class="w-full text-left px-3 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-200 border-b border-gray-100 dark:border-gray-700 last:border-0"
                            :class="selectedModel === model.id ? 'bg-gray-50 dark:bg-gray-700' : ''"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex-1 min-w-0">
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ model.name }}</span>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">{{ model.description }}</div>
                                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">${{ model.pricePer1k }}/1k tokens</div>
                                </div>
                                <div v-if="selectedModel === model.id" class="text-green-600 ml-3 flex-shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <button
                @click="goToAiSettings"
                class="w-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 px-4 py-2.5 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-all duration-200 text-sm font-medium flex items-center justify-center gap-2"
            >
                Instructions IA
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-4">
            <div class="space-y-1">
                <div
                    v-for="c in conversations"
                    :key="c?.id"
                    class="group relative"
                >
                    <div
                        @click="switchConversation(c.id)"
                        class="flex items-center justify-between p-3 rounded-lg cursor-pointer transition-all duration-200"
                        :class="c.id === activeId ? 'bg-gray-100 dark:bg-gray-700 border-l-4 border-gray-900 dark:border-gray-400' : 'hover:bg-gray-50 dark:hover:bg-gray-700'"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ c.title }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ c.messages?.length || 0 }} messages</div>
                        </div>
                        <button
                            @click="confirmDelete(c.id, $event)"
                            class="opacity-0 group-hover:opacity-100 transition-opacity duration-200 p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-600 flex-shrink-0"
                            title="Supprimer"
                        >
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>{{ sessionStats.messagesSent }} messages</span>
                <span>{{ sessionStats.totalTokens.toLocaleString() }} tokens</span>
                <span>{{ formattedStats.cost }}</span>
            </div>
        </div>
    </aside>

 <!-- ============================================================
    MAIN CHAT AREA
    ============================================================ -->
    <main class="flex-1 flex flex-col bg-gray-50 dark:bg-gray-900 min-w-0">

        <!-- Chat Header -->
        <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex-shrink-0">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3 min-w-0">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 truncate">
                        {{ activeConversation?.title || 'Chat' }}
                    </h2>
                    <button
                        @click="toggleThinking"
                        class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 flex-shrink-0"
                        :class="showThinking ? 'text-gray-900 dark:text-gray-100 font-medium' : ''"
                    >
                        Thinking {{ showThinking ? '✓' : '' }}
                    </button>
                    <button
                        @click="showExportModal = true"
                        class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 flex-shrink-0"
                    >
                        Export
                    </button>
                </div>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <button
                        @click="toggleCommands"
                        class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        title="Voir les commandes"
                    >
                        Commandes
                    </button>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ messages.length }} messages</div>
                </div>
            </div>
        </div>

       <!-- Messages Container -->
<div id="chat-box" class="flex-1 overflow-y-auto p-6">

    <!-- Message d'accueil minimaliste en haut -->
    <div v-if="!hasMessages && !isStreaming && activeConversation" class="text-center py-2">
        <p class="text-sm text-gray-400 dark:text-gray-500">
            Conseils en typographie, couleurs, mise en page, accessibilite et plus encore. Envoie un message pour lancer la conversation.
        </p>
    </div>

    <!-- Pas de conversation active -->
    <div v-if="!activeConversation" class="flex items-center justify-center h-full">
        <div class="text-center">
            <p class="text-gray-500 dark:text-gray-400">Cree une conversation</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">Ctrl+N | Ctrl+Shift+D</p>
        </div>
    </div>

    <!-- Message d'accueil personnalisé DesignMentor (centré) -->
    <div v-else-if="!hasMessages && !isStreaming && !isConnecting" class="flex items-center justify-center h-full">
        <div class="text-center max-w-md">
            <div class="text-6xl mb-4">🎨</div>
            <p class="text-gray-500 dark:text-gray-400 text-lg font-medium">DesignMentor</p>
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-2">Ton assistant design UI/UX</p>
            <div class="mt-4 flex flex-wrap gap-2 justify-center">
                <button
                    v-for="suggestion in quickSuggestions.slice(0, 3)"
                    :key="suggestion"
                    @click="useSuggestion(suggestion)"
                    class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-full text-xs hover:bg-gray-200 dark:hover:bg-gray-600 transition-all duration-200"
                >
                    {{ suggestion.substring(0, 30) }}...
                </button>
            </div>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-4">Modele: {{ getSelectedModelInfo().name }}</p>
        </div>
    </div>

            <!-- Messages et streaming -->
            <div v-else class="space-y-4">
                <!-- Messages historiques -->
                <div 
                    v-for="(m, i) in messages" 
                    :key="i"
                    class="animate-message-in"
                >
                    <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                        <div class="max-w-xl">
                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1 px-2">
                                {{ m.role === 'user' ? 'Vous' : (getMessageModelName(m) || getSelectedModelInfo().name) }}
                            </div>
                            <div
                                class="px-4 py-2 rounded-lg whitespace-pre-wrap text-sm"
                                :class="m.role === 'user'
                                    ? 'bg-gray-900 text-white dark:bg-gray-700 dark:text-white'
                                    : 'bg-white text-gray-900 border border-gray-200 dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700'"
                            >
                                <span v-if="m.role === 'user' && isCommand(m.content)" class="text-blue-400 font-mono text-xs">
                                    {{ getCommandName(m.content) }}
                                </span>
                                <MarkdownRenderer :content="m.content" />
                                <div v-if="m.reasoning && showThinking" class="mt-2 p-2 bg-gray-50 dark:bg-gray-700 rounded text-xs text-gray-600 dark:text-gray-300">
                                    <span class="font-medium">Reflexion:</span>
                                    <pre class="whitespace-pre-wrap font-sans mt-1">{{ m.reasoning }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- === INDICATEUR "L'IA reflechit..." QUI APPARAIT IMMEDIATEMENT === -->
                <div v-if="isConnecting && !streamedContent && !streamedReasoning" class="flex justify-start animate-message-in">
                    <div class="max-w-xl">
                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1 px-2">
                            {{ getSelectedModelInfo().name }}
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-4 py-2 rounded-lg whitespace-pre-wrap text-sm text-gray-500 dark:text-gray-400">
                            <span class="flex items-center gap-2">
                                <span class="animate-pulse">●</span>
                                <span class="animate-pulse" style="animation-delay: 0.2s">●</span>
                                <span class="animate-pulse" style="animation-delay: 0.4s">●</span>
                                <span>L'IA reflechit...</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Stream en cours -->
                <div v-if="isStreaming && (streamedContent || streamedReasoning)" class="flex justify-start animate-message-in">
                    <div class="max-w-xl">
                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1 px-2">
                            {{ getSelectedModelInfo().name }}
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-4 py-2 rounded-lg whitespace-pre-wrap text-sm text-gray-900 dark:text-gray-100">
                            <div v-if="hasReasoning" class="mb-2 p-2 bg-gray-50 dark:bg-gray-700 rounded text-xs text-gray-600 dark:text-gray-300 border border-gray-100 dark:border-gray-600">
                                <div class="font-medium text-gray-500 dark:text-gray-400 mb-1">Reasoning:</div>
                                <pre class="whitespace-pre-wrap font-sans">{{ streamedReasoning }}</pre>
                            </div>
                            <span v-if="streamedContent">
                                <MarkdownRenderer :content="streamedContent" />
                            </span>
                            <span v-else class="text-gray-400">▌</span>
                            <span class="inline-block w-0.5 h-4 bg-gray-500 animate-pulse ml-0.5"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="loading && !isStreaming && !isConnecting" class="flex justify-start mt-4 animate-fade-in">
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ getSelectedModelInfo().name }} reflechit...
                </div>
            </div>
        </div>

        <!-- Input Area -->
        <div class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 p-4 flex-shrink-0">
            <div class="flex gap-2">
                <input
                    v-model="message"
                    @keyup.enter="sendMessage"
                    type="text"
                    class="flex-1 px-4 py-2.5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-400 focus:border-transparent text-sm placeholder-gray-400 dark:placeholder-gray-500"
                    :disabled="loading || !activeId || isStreaming || isConnecting"
                    placeholder="Pose une question... ou utilise /help"
                />
                <button
                    v-if="!isStreaming && !isConnecting"
                    @click="sendMessage"
                    class="px-5 py-2.5 bg-gray-900 dark:bg-gray-700 text-white rounded-lg hover:bg-gray-800 dark:hover:bg-gray-600 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium flex-shrink-0"
                    :disabled="loading || !activeId"
                >
                    Envoyer
                </button>
                <button
                    v-else
                    @click="cancelStream"
                    class="px-5 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all duration-200 text-sm font-medium flex-shrink-0"
                >
                    Annuler
                </button>
            </div>
            
            <!-- Commandes Panel -->
            <div v-if="showCommands" class="mt-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 animate-fade-in">
                <div class="text-xs text-gray-600 dark:text-gray-300 mb-2 font-medium">Commandes disponibles:</div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="font-mono text-blue-600 dark:text-blue-400">/help</div>
                    <div class="text-gray-600 dark:text-gray-300">Afficher l'aide</div>
                    <div class="font-mono text-blue-600 dark:text-blue-400">/commands</div>
                    <div class="text-gray-600 dark:text-gray-300">Lister tes commandes</div>
                    <div class="font-mono text-blue-600 dark:text-blue-400">/debug</div>
                    <div class="text-gray-600 dark:text-gray-300">Analyser du code</div>
                    <div class="font-mono text-blue-600 dark:text-blue-400">/eli5</div>
                    <div class="text-gray-600 dark:text-gray-300">Expliquer simplement</div>
                    <div class="font-mono text-blue-600 dark:text-blue-400">/review</div>
                    <div class="text-gray-600 dark:text-gray-300">Code review</div>
                </div>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Va dans Instructions IA pour creer tes propres commandes
                </div>
            </div>
        </div>
    </main>

    <!-- ============================================================
    MODALS
    ============================================================ -->

    <!-- Delete -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 animate-fade-in">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4 animate-modal-in">
            <div class="text-center">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Supprimer la conversation</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Cette action est irreversible.</p>
                <div class="flex gap-3">
                    <button @click="deleteConversation" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all duration-200 text-sm font-medium">Supprimer</button>
                    <button @click="cancelDelete" class="flex-1 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-all duration-200 text-sm font-medium">Annuler</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Export -->
    <div v-if="showExportModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 animate-fade-in">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4 animate-modal-in">
            <div class="text-center">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Exporter la conversation</h3>
                <div class="mb-4">
                    <select v-model="exportFormat" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="md">Markdown (.md)</option>
                        <option value="txt">Texte (.txt)</option>
                        <option value="json">JSON (.json)</option>
                    </select>
                </div>
                <div class="mb-4 text-left">
                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input type="checkbox" v-model="exportOptions.includeMetadata" /> Metadonnees
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input type="checkbox" v-model="exportOptions.includeReasoning" /> Reflexions
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input type="checkbox" v-model="exportOptions.includeStats" /> Statistiques
                    </label>
                </div>
                <div class="flex gap-3">
                    <button @click="exportConversation" class="flex-1 px-4 py-2 bg-gray-900 dark:bg-gray-700 text-white rounded-lg hover:bg-gray-800 dark:hover:bg-gray-600 transition-all duration-200 text-sm font-medium">Exporter</button>
                    <button @click="showExportModal = false" class="flex-1 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-all duration-200 text-sm font-medium">Annuler</button>
                </div>
            </div>
        </div>
    </div>

</div>
</div>
</template>

<style scoped>
@keyframes messageIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes modalIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

.animate-message-in { animation: messageIn 0.2s ease-out; }
.animate-fade-in { animation: fadeIn 0.2s ease-out; }
.animate-modal-in { animation: modalIn 0.2s ease-out; }
.animate-pulse { animation: pulse 1.2s ease-in-out infinite; }

::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: transparent;
}

::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

.dark ::-webkit-scrollbar-thumb {
    background: #4b5563;
}

.dark ::-webkit-scrollbar-thumb:hover {
    background: #6b7280;
}
</style>