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

        // 🔥 sécurité JSON
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
}

/**
 * DELETE CONVERSATION (front only)
 */
const deleteConversation = (id) => {
    conversations.value = conversations.value.filter(c => c?.id !== id)

    if (activeId.value === id) {
        activeId.value = conversations.value[0]?.id ?? null
    }
}

/**
 * SCROLL
 */
const scrollToBottom = () => {
    const el = document.getElementById('chat-box')
    if (el) el.scrollTop = el.scrollHeight
}

/**
 * SEND MESSAGE (FIXED)
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
<div class="flex h-screen bg-zinc-950 text-white">

    <!-- SIDEBAR -->
    <aside class="w-72 border-r border-zinc-800 p-4 flex flex-col">

        <button
            @click="newConversation"
            class="w-full bg-zinc-800 p-3 rounded mb-4 hover:bg-zinc-700"
        >
            + Nouvelle conversation
        </button>

        <div class="flex-1 overflow-y-auto space-y-2">

            <div
                v-for="c in conversations"
                :key="c?.id"
                class="p-3 rounded cursor-pointer"
                @click="switchConversation(c.id)"
                :class="c.id === activeId ? 'bg-zinc-800' : 'hover:bg-zinc-900'"
            >
                <div class="text-sm font-medium truncate">
                    {{ c.title }}
                </div>

                <div class="text-xs text-zinc-500">
                    {{ c.messages?.length ?? 0 }} messages
                </div>
            </div>

        </div>

    </aside>

    <!-- CHAT -->
    <main class="flex-1 flex flex-col">

        <div
            id="chat-box"
            class="flex-1 overflow-y-auto p-6 space-y-4"
        >

            <div v-if="!activeConversation" class="text-zinc-500 text-center mt-10">
                Crée une conversation 👈
            </div>

            <div v-for="(m, i) in messages" :key="i">
                <div :class="m.role === 'user' ? 'text-right' : 'text-left'">

                    <div
                        class="inline-block p-3 rounded max-w-xl whitespace-pre-wrap"
                        :class="m.role === 'user'
                            ? 'bg-blue-600 ml-auto'
                            : 'bg-zinc-800'"
                    >
                        {{ m.content }}
                    </div>

                </div>
            </div>

            <div v-if="loading" class="text-zinc-400 text-center">
                DevMentor réfléchit...
            </div>

        </div>

        <!-- INPUT -->
        <div class="p-4 border-t border-zinc-800 flex gap-2">

            <input
                v-model="message"
                @keyup.enter="sendMessage"
                class="flex-1 p-3 bg-zinc-900 rounded outline-none"
                :disabled="loading || !activeId"
                placeholder="Pose une question..."
            />

            <button
                @click="sendMessage"
                class="bg-blue-600 px-4 rounded disabled:opacity-50"
                :disabled="loading || !activeId"
            >
                Envoyer
            </button>

        </div>

    </main>

</div>
</template>