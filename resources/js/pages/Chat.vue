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

// Modeles qui fonctionnent sur OpenRouter
const models = [
    { 
        id: 'openai/gpt-4o-mini', 
        name: 'GPT-4o Mini', 
        provider: 'OpenAI',
        description: 'Rapide, efficace et pas cher'
    },
    { 
        id: 'openai/gpt-4o', 
        name: 'GPT-4o', 
        provider: 'OpenAI',
        description: 'Plus puissant, bon pour les taches complexes'
    },
    { 
        id: 'openai/gpt-3.5-turbo', 
        name: 'GPT-3.5 Turbo', 
        provider: 'OpenAI',
        description: 'Le moins cher, rapide pour les reponses simples'
    }
]

const selectedModel = ref('openai/gpt-4o-mini')

// Hook de streaming
const { data, isFetching, isStreaming: streamActive, send, cancel } = useStream(
    '/chat/stream',
    {
        onData: () => {
            // Le chunk est automatiquement concaténé dans `data`
            const content = extractContent(data.value || '')
            const reasoning = extractReasoning(data.value || '')
            streamedContent.value = content
            streamedReasoning.value = reasoning
            fullStreamedResponse.value = data.value || ''
            
            // Scroll automatique
            nextTick(() => scrollToBottom())
        },
        onFinish: () => {
            // Sauvegarder le message final dans l'historique
            if (activeConversation.value && fullStreamedResponse.value) {
                const content = extractContent(fullStreamedResponse.value)
                const reasoning = extractReasoning(fullStreamedResponse.value)
                
                const conv = activeConversation.value
                if (conv) {
                    if (!Array.isArray(conv.messages)) {
                        conv.messages = []
                    }
                    
                    // Ajouter à l'UI
                    conv.messages.push({
                        role: 'assistant',
                        content: content,
                        reasoning: reasoning || null,
                        model: selectedModel.value
                    })
                }
            }
            
            isStreaming.value = false
            streamedContent.value = ''
            streamedReasoning.value = ''
            fullStreamedResponse.value = ''
            message.value = ''
            loading.value = false
        },
        onError: (err) => {
            console.error('Erreur streaming:', err)
            isStreaming.value = false
            loading.value = false
            streamedContent.value = ''
            streamedReasoning.value = ''
            
            // Ajouter un message d'erreur
            const conv = activeConversation.value
            if (conv) {
                conv.messages.push({
                    role: 'assistant',
                    content: 'Erreur: ' + err.message,
                    model: null
                })
            }
        }
    }
)

/**
 * Extrait le contenu principal (sans le reasoning)
 */
const extractContent = (text) => {
    if (!text) return ''
    return text.replace(/\[REASONING\][\s\S]*?\[\/REASONING\]/g, '').trim()
}

/**
 * Extrait le reasoning des marqueurs
 */
const extractReasoning = (text) => {
    if (!text) return ''
    const matches = text.match(/\[REASONING\]([\s\S]*?)\[\/REASONING\]/g)
    if (!matches) return ''
    return matches
        .map((m) => m.replace(/\[REASONING\]/g, '').replace(/\[\/REASONING\]/g, ''))
        .join('')
}

/**
 * Vérifier si le contenu contient du reasoning
 */
const hasReasoning = computed(() => {
    return streamedReasoning.value && streamedReasoning.value.length > 0
})

/**
 * Annuler le streaming
 */
const cancelStream = () => {
    if (streamActive.value) {
        cancel()
        isStreaming.value = false
        loading.value = false
        
        // Ajouter un message d'annulation
        const conv = activeConversation.value
        if (conv) {
            conv.messages.push({
                role: 'assistant',
                content: 'Réponse annulée.',
                model: null
            })
        }
    }
}

/**
 * Sauvegarder le modele prefere de l'utilisateur
 */
const savePreferredModel = async (modelId) => {
    try {
        const response = await fetch('/user/ai-profile', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({
                preferred_model: modelId
                // Ne pas envoyer ai_about, ai_behavior, ai_commands
            })
        })
        
        if (!response.ok) {
            throw new Error('Erreur sauvegarde')
        }
    } catch (err) {
        console.error('Erreur sauvegarde modele:', err)
    }
}


/**
 * Charger le modele prefere depuis le serveur
 */
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

/**
 * Selectionner un modele (ne touche pas aux instructions perso)
 */
const selectModel = async (modelId) => {
    selectedModel.value = modelId
    showModelSelector.value = false
    await savePreferredModel(modelId)
}

/**
 * Toggle modele selector
 */
const toggleModelSelector = () => {
    showModelSelector.value = !showModelSelector.value
}

/**
 * Infos du modele selectionne
 */
const getSelectedModelInfo = () => {
    return models.find(m => m.id === selectedModel.value) || models[0]
}

/**
 * Obtenir le nom du modele pour un message (si disponible)
 */
const getMessageModelName = (message) => {
    if (message.model) {
        const model = models.find(m => m.id === message.model)
        return model ? model.name : message.model.split('/').pop()
    }
    return null
}

/**
 * ACTIVE CONVERSATION SAFE
 */
const activeConversation = computed(() => {
    return conversations.value.find(c => c?.id === activeId.value) || null
})

/**
 * MESSAGES SAFE
 */
const messages = computed(() => {
    return activeConversation.value?.messages ?? []
})

/**
 * NAVIGATION VERS PARAMETRES IA
 */
const goToAiSettings = () => {
    router.visit('/settings/ai')
}

/**
 * TOGGLE COMMANDES
 */
const toggleCommands = () => {
    showCommands.value = !showCommands.value
}

/**
 * CREATE CONVERSATION
 */
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

        let data = null
        try {
            data = await res.json()
        } catch (e) {
            throw new Error("Reponse serveur invalide")
        }

        if (!res.ok) {
            throw new Error(data?.message || "Erreur creation conversation")
        }

        if (!data || !data.id) {
            throw new Error("Conversation invalide (pas d'id)")
        }

        const newConv = {
            id: data.id,
            title: data.title ?? 'Nouvelle conversation',
            messages: []
        }

        conversations.value.unshift(newConv)
        activeId.value = newConv.id

    } catch (err) {
        console.error('Erreur creation conversation:', err.message)
    }
}

/**
 * SWITCH CONVERSATION
 */
const switchConversation = (id) => {
    activeId.value = id
    nextTick(() => scrollToBottom())
}

/**
 * DELETE CONVERSATION
 */
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

        let data = null
        try {
            data = await res.json()
        } catch (e) {
            throw new Error("Reponse serveur invalide")
        }

        if (!res.ok) {
            throw new Error(data?.message || "Erreur suppression")
        }

        conversations.value = conversations.value.filter(
            c => c?.id !== conversationToDelete.value
        )

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

/**
 * SCROLL
 */
const scrollToBottom = () => {
    const el = document.getElementById('chat-box')
    if (el) {
        el.scrollTo({
            top: el.scrollHeight,
            behavior: 'smooth'
        })
    }
}

/**
 * DETECTER ET AFFICHER LES COMMANDES
 */
const isCommand = (text) => {
    return text.startsWith('/')
}

const getCommandName = (text) => {
    const parts = text.split(' ')
    return parts[0]
}

/**
 * SEND MESSAGE AVEC STREAMING
 */
const sendMessage = async () => {
    if (!message.value.trim() || loading.value || !activeId.value) return

    const text = message.value
    
    // Ajouter le message utilisateur à l'historique (UI)
    const conv = activeConversation.value
    if (!conv) return

    if (!Array.isArray(conv.messages)) {
        conv.messages = []
    }

    conv.messages.push({
        role: 'user',
        content: text,
        model: null
    })

    // Sauvegarder le message utilisateur en DB et recharger
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
        
        // Mettre à jour le titre si nécessaire
        if (data.conversation_title && conv.title === 'Nouvelle conversation') {
            conv.title = data.conversation_title
        }
    } catch (err) {
        console.error('Erreur sauvegarde message utilisateur:', err)
    }

    // Réinitialiser le streaming
    streamedContent.value = ''
    streamedReasoning.value = ''
    fullStreamedResponse.value = ''
    isStreaming.value = true
    loading.value = true

    // Envoyer la requête de streaming
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
        loading.value = false
    }
}

onMounted(() => {
    loadPreferredModel()
})
</script>

<template>
<div class="flex h-screen bg-gray-50">

    <!-- SIDEBAR -->
    <aside class="w-80 bg-white border-r border-gray-200 flex flex-col">
        <div class="p-4 border-b border-gray-200">
            <button
                @click="newConversation"
                class="w-full bg-gray-900 text-white px-4 py-2.5 rounded-lg hover:bg-gray-800 transition-all duration-200 text-sm font-medium"
            >
                + Nouvelle conversation
            </button>
        </div>

        <!-- Selection du modele -->
        <div class="p-4 border-b border-gray-200">
            <div class="relative">
                <button
                    @click="toggleModelSelector"
                    class="w-full flex items-center justify-between px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 transition-all duration-200 text-sm"
                >
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                        <div class="text-left">
                            <div class="text-gray-700 font-medium">{{ getSelectedModelInfo().name }}</div>
                            <div class="text-xs text-gray-500">{{ getSelectedModelInfo().provider }}</div>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-gray-500 transition-transform" :class="showModelSelector ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <!-- Dropdown modeles -->
                <div v-if="showModelSelector" class="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-20">
                    <div class="py-1">
                        <button
                            v-for="model in models"
                            :key="model.id"
                            @click="selectModel(model.id)"
                            class="w-full text-left px-3 py-3 hover:bg-gray-50 transition-all duration-200 border-b border-gray-100 last:border-0"
                            :class="selectedModel === model.id ? 'bg-gray-50' : ''"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-900">{{ model.name }}</span>
                                        <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full">{{ model.provider }}</span>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">{{ model.description }}</div>
                                </div>
                                <div v-if="selectedModel === model.id" class="text-green-600 ml-3">
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
        
        <div class="p-4 border-b border-gray-200">
            <button
                @click="goToAiSettings"
                class="w-full bg-gray-100 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-200 transition-all duration-200 text-sm font-medium flex items-center justify-center gap-2"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
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
                        :class="c.id === activeId 
                            ? 'bg-gray-100' 
                            : 'hover:bg-gray-50'"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 truncate">
                                {{ c.title }}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ c.messages?.length ?? 0 }} messages
                            </div>
                        </div>
                        
                        <button
                            @click="confirmDelete(c.id, $event)"
                            class="opacity-0 group-hover:opacity-100 transition-opacity duration-200 p-1 rounded hover:bg-gray-200"
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
    </aside>

    <!-- MAIN CHAT AREA -->
    <main class="flex-1 flex flex-col bg-gray-50">

        <!-- Chat Header -->
        <div class="bg-white border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ activeConversation?.title || 'Chat' }}
                </h2>
                <div class="flex items-center gap-3">
                    <button
                        @click="toggleCommands"
                        class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1"
                        title="Voir les commandes"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Commandes
                    </button>
                    <div class="text-sm text-gray-500">
                        {{ messages.length }} messages
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages Container -->
        <div
            id="chat-box"
            class="flex-1 overflow-y-auto p-6"
        >
            <div v-if="!activeConversation" class="flex items-center justify-center h-full">
                <div class="text-center">
                    <p class="text-gray-500">Cree une conversation</p>
                </div>
            </div>

            <div v-else-if="messages.length === 0 && !isStreaming" class="flex items-center justify-center h-full">
                <div class="text-center">
                    <p class="text-gray-500">Envoie un message pour commencer</p>
                    <p class="text-xs text-gray-400 mt-2">Modele actuel: {{ getSelectedModelInfo().name }} ({{ getSelectedModelInfo().provider }})</p>
                    <p class="text-xs text-gray-400">Astuce: utilise /help pour voir les commandes</p>
                </div>
            </div>

            <div v-else class="space-y-4">
                <!-- Messages historiques -->
                <div 
                    v-for="(m, i) in messages" 
                    :key="i"
                    class="animate-message-in"
                >
                    <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                        <div class="max-w-xl">
                            <div class="text-xs text-gray-500 mb-1 px-2">
                                {{ m.role === 'user' ? 'Vous' : (getMessageModelName(m) || getSelectedModelInfo().name) }}
                            </div>
                            <div
                                class="px-4 py-2 rounded-lg whitespace-pre-wrap text-sm"
                                :class="m.role === 'user'
                                    ? 'bg-gray-900 text-white'
                                    : 'bg-white text-gray-900 border border-gray-200'"
                            >
                                <span v-if="m.role === 'user' && isCommand(m.content)" class="text-blue-400 font-mono text-xs">
                                    {{ getCommandName(m.content) }}
                                </span>
                                <MarkdownRenderer :content="m.content" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stream en cours -->
                <div v-if="isStreaming" class="flex justify-start animate-message-in">
                    <div class="max-w-xl">
                        <div class="text-xs text-gray-500 mb-1 px-2">
                            {{ getSelectedModelInfo().name }}
                        </div>
                        <div class="bg-white text-gray-900 border border-gray-200 px-4 py-2 rounded-lg whitespace-pre-wrap text-sm">
                            <!-- Affichage du reasoning (optionnel) -->
                            <div v-if="hasReasoning" class="mb-2 p-2 bg-gray-50 rounded text-xs text-gray-600 border border-gray-100">
                                <div class="font-medium text-gray-500 mb-1">🧠 Reasoning:</div>
                                <pre class="whitespace-pre-wrap font-sans">{{ streamedReasoning }}</pre>
                            </div>
                            <!-- Contenu principal -->
                            <span v-if="streamedContent">
                                <MarkdownRenderer :content="streamedContent" />
                            </span>
                            <span v-else class="text-gray-400">▌</span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="loading && !isStreaming" class="flex justify-start mt-4 animate-fade-in">
                <div class="bg-white border border-gray-200 rounded-lg px-4 py-2 text-sm text-gray-500">
                    {{ getSelectedModelInfo().name }} reflechit...
                </div>
            </div>
        </div>

        <!-- Input Area -->
        <div class="bg-white border-t border-gray-200 p-4">
            <div class="flex gap-2">
                <div class="flex-1 relative">
                    <input
                        v-model="message"
                        @keyup.enter="sendMessage"
                        type="text"
                        class="w-full px-4 py-2.5 bg-white text-gray-900 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-400 focus:border-transparent text-sm placeholder-gray-400"
                        :disabled="loading || !activeId || isStreaming"
                        placeholder="Pose une question... ou utilise /help"
                    />
                </div>
                <button
                    v-if="!isStreaming"
                    @click="sendMessage"
                    class="px-5 py-2.5 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium"
                    :disabled="loading || !activeId"
                >
                    Envoyer
                </button>
                <button
                    v-else
                    @click="cancelStream"
                    class="px-5 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all duration-200 text-sm font-medium"
                >
                    Annuler
                </button>
            </div>
            
            <!-- Commandes Panel -->
            <div v-if="showCommands" class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-200 animate-fade-in">
                <div class="text-xs text-gray-600 mb-2">Commandes disponibles:</div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="font-mono text-blue-600">/help</div>
                    <div class="text-gray-600">Afficher l'aide</div>
                    <div class="font-mono text-blue-600">/commands</div>
                    <div class="text-gray-600">Lister tes commandes</div>
                    <div class="font-mono text-blue-600">/debug</div>
                    <div class="text-gray-600">Analyser du code</div>
                    <div class="font-mono text-blue-600">/eli5</div>
                    <div class="text-gray-600">Expliquer simplement</div>
                    <div class="font-mono text-blue-600">/review</div>
                    <div class="text-gray-600">Code review</div>
                </div>
                <div class="mt-2 text-xs text-gray-500">
                    Va dans Instructions IA pour creer tes propres commandes
                </div>
            </div>
        </div>
    </main>

    <!-- DELETE CONFIRMATION MODAL -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 animate-fade-in">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4 animate-modal-in">
            <div class="text-center">
                <h3 class="text-lg font-medium text-gray-900 mb-2">
                    Supprimer la conversation
                </h3>
                <p class="text-sm text-gray-500 mb-6">
                    Etes-vous sur de vouloir supprimer cette conversation ? Cette action est irreversible.
                </p>
                <div class="flex gap-3">
                    <button
                        @click="deleteConversation"
                        class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all duration-200 text-sm font-medium"
                    >
                        Supprimer
                    </button>
                    <button
                        @click="cancelDelete"
                        class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all duration-200 text-sm font-medium"
                    >
                        Annuler
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>
</template>

<style scoped>
@keyframes messageIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes modalIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.animate-message-in {
    animation: messageIn 0.2s ease-out;
}

.animate-fade-in {
    animation: fadeIn 0.2s ease-out;
}

.animate-modal-in {
    animation: modalIn 0.2s ease-out;
}
</style>