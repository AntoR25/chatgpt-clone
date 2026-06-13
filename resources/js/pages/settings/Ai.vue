<template>
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <h1 class="text-2xl font-bold mb-6">Instructions IA</h1>
            
            <div class="mb-6">
                <label class="block font-semibold mb-2">A propos de vous</label>
                <textarea 
                    v-model="form.about" 
                    rows="4" 
                    class="w-full border rounded p-2"
                    placeholder="Ex: Je suis developpeur PHP, 5 ans d'experience...">
                </textarea>
            </div>

            <div class="mb-6">
                <label class="block font-semibold mb-2">Comportement attendu</label>
                <textarea 
                    v-model="form.behavior" 
                    rows="4" 
                    class="w-full border rounded p-2"
                    placeholder="Ex: Reponses concises, exemples de code, pas de blabla...">
                </textarea>
            </div>

            <div class="mb-6">
                <label class="block font-semibold mb-2">Commandes personnalisees</label>
                <textarea 
                    v-model="commandsText" 
                    rows="8" 
                    class="w-full border rounded p-2 font-mono"
                    placeholder='{
    "/debug": "Analyse ce code, trouve les bugs prioritaires",
    "/eli5": "Explique comme si javais 5 ans avec une analogie",
    "/review": "Code review detaillee avec suggestions"
}'>
                </textarea>
                <div v-if="jsonError" class="text-red-500 text-sm mt-1">{{ jsonError }}</div>
            </div>

            <div class="flex gap-3">
                <button 
                    @click="goToChat" 
                    class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                    Retour au chat
                </button>
                <button 
                    @click="save" 
                    class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Sauvegarder
                </button>
            </div>
            
            <div v-if="saved" class="mt-4 text-green-600">Sauvegarde reussie</div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

const form = ref({
    about: '',
    behavior: ''
})
const commandsText = ref('{}')
const saved = ref(false)
const jsonError = ref('')

const goToChat = () => {
    router.visit('/chat')
}

const loadProfile = async () => {
    const response = await fetch('/user/ai-profile')
    const data = await response.json()
    form.value.about = data.ai_about || ''
    form.value.behavior = data.ai_behavior || ''
    commandsText.value = JSON.stringify(data.ai_commands || {}, null, 2)
}

const save = async () => {
    jsonError.value = ''
    
    let parsedCommands = {}
    try {
        parsedCommands = JSON.parse(commandsText.value)
    } catch(e: any) {
        jsonError.value = 'JSON invalide: ' + e.message
        return
    }
    
    const response = await fetch('/user/ai-profile', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({
            ai_about: form.value.about,
            ai_behavior: form.value.behavior,
            ai_commands: parsedCommands
        })
    })
    
    if (response.ok) {
        saved.value = true
        setTimeout(() => saved.value = false, 2000)
    }
}

onMounted(() => {
    loadProfile()
})
</script>