<script setup>
import { ref, computed, nextTick } from 'vue'

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
 * CREATE CONVERSATION (DB ONLY - IMPORTANT FIX)
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
            throw new Error("Réponse serveur invalide")
        }

        if (!res.ok) {
            throw new Error(data?.message || "Erreur création conversation")
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
        console.error('Erreur création conversation:', err.message)
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
            throw new Error("Réponse serveur invalide")
        }

        if (!res.ok) {
            throw new Error(data?.message || "Erreur suppression")
        }

        // ✔ suppression UI seulement si backend OK
        conversations.value = conversations.value.filter(
            c => c?.id !== conversationToDelete.value
        )

        if (activeId.value === conversationToDelete.value) {
            activeId.value = conversations.value[0]?.id ?? null
        }

    } catch (err) {
        console.error('Erreur suppression:', err.message)
        alert('❌ ' + err.message)
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
 * SEND MESSAGE (COMME DANS TON CODE ORIGINAL)
 */
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
        content: text
    })

    loading.value = true

    await nextTick()
    scrollToBottom()

    try {
        const res = await fetch('/chat/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                message: text,
                conversation_id: activeId.value
            })
        })

        let data
        try {
            data = await res.json()
        } catch {
            throw new Error('Réponse serveur invalide')
        }

        if (!res.ok) {
            throw new Error(data?.message || 'Erreur API')
        }

        conv.messages.push({
            role: 'assistant',
            content: data.answer ?? 'Réponse vide'
        })

        await nextTick()
        scrollToBottom()

        // Mise à jour du titre de la conversation 
        if (
            data.conversation_title &&
            conv.title === 'Nouvelle conversation'
        ) {
            conv.title = data.conversation_title
        }

    } catch (err) {
        conv.messages.push({
            role: 'assistant',
            content: '❌ ' + err.message
        })
    }

    loading.value = false

    await nextTick()
    scrollToBottom()
}
</script>

<template>
<div class="flex h-screen bg-gray-50">

    <!-- SIDEBAR -->
    <aside class="w-72 bg-white border-r border-gray-200 flex flex-col">
        <div class="p-4 border-b border-gray-200">
            <button
                @click="newConversation"
                class="w-full bg-gray-900 text-white px-4 py-2 rounded-md hover:bg-gray-800 transition-all duration-200 text-sm font-medium"
            >
                + Nouvelle conversation
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
                        class="flex items-center justify-between p-3 rounded-md cursor-pointer transition-all duration-200"
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
                <div class="text-sm text-gray-500">
                    {{ messages.length }} messages
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
                    <p class="text-gray-500">Crée une conversation 👈</p>
                </div>
            </div>

            <div v-else-if="messages.length === 0" class="flex items-center justify-center h-full">
                <div class="text-center">
                    <p class="text-gray-500">Envoie un message pour commencer</p>
                </div>
            </div>

            <div v-else class="space-y-4">
                <div 
                    v-for="(m, i) in messages" 
                    :key="i"
                    class="animate-message-in"
                >
                    <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                        <div class="max-w-xl">
                            <div class="text-xs text-gray-500 mb-1 px-2">
                                {{ m.role === 'user' ? 'Vous' : 'Assistant' }}
                            </div>
                            <div
                                class="px-4 py-2 rounded-lg whitespace-pre-wrap text-sm"
                                :class="m.role === 'user'
                                    ? 'bg-gray-900 text-white'
                                    : 'bg-white text-gray-900 border border-gray-200'"
                            >
                                {{ m.content }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="loading" class="flex justify-start mt-4 animate-fade-in">
                <div class="bg-white border border-gray-200 rounded-lg px-4 py-2 text-sm text-gray-500">
                    Réfléchit...
                </div>
            </div>
        </div>

        <!-- Input Area -->
        <div class="bg-white border-t border-gray-200 p-4">
            <div class="flex gap-2">
                <input
                    v-model="message"
                    @keyup.enter="sendMessage"
                    type="text"
                    class="flex-1 px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400 focus:border-transparent text-sm placeholder-gray-400"
                    :disabled="loading || !activeId"
                    placeholder="Pose une question..."
                />
                <button
                    @click="sendMessage"
                    class="px-4 py-2 bg-gray-900 text-white rounded-md hover:bg-gray-800 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium"
                    :disabled="loading || !activeId"
                >
                    Envoyer
                </button>
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
                    Êtes-vous sûr de vouloir supprimer cette conversation ? Cette action est irréversible.
                </p>
                <div class="flex gap-3">
                    <button
                        @click="deleteConversation"
                        class="flex-1 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-all duration-200 text-sm font-medium"
                    >
                        Supprimer
                    </button>
                    <button
                        @click="cancelDelete"
                        class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-all duration-200 text-sm font-medium"
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