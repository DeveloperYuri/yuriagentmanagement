<script setup>
import { ref } from 'vue'; // Tambahkan ref
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue'; // Import Modal bawaan
import { Head, useForm } from '@inertiajs/vue3';

defineProps({ agents: Array });

const showModal = ref(false); // State untuk modal

const form = useForm({
    code: '',
    name: '',
});

const submit = () => {
    form.post(route('agents.store'), {
        onSuccess: () => {
            form.reset();
            showModal.value = false; // Tutup modal setelah sukses
        },
    });
};
</script>

<template>
    <Head title="Daftar Agents" />

    <AuthenticatedLayout>
        <template #header>Daftar Agents</template>

        <div class="space-y-6">
            <div class="flex justify-between items-center bg-white p-4 rounded-lg shadow-sm border">
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Data Master Agent</h3>
                    <p class="text-xs text-gray-500">Total: {{ agents.length }} agent</p>
                </div>
                <button 
                    @click="showModal = true" 
                    class="bg-indigo-600 text-white px-5 py-2 rounded-md font-bold text-sm hover:bg-indigo-700 transition flex items-center gap-2"
                >
                    <span>+</span> Tambah Agent
                </button>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-600 uppercase font-bold text-[10px] border-b">
                        <tr>
                            <th class="px-6 py-4">Kode</th>
                            <th class="px-6 py-4">Nama Agent</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="agent in agents" :key="agent.id" class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 font-mono text-indigo-600 font-bold">{{ agent.code }}</td>
                            <td class="px-6 py-4 text-gray-800">{{ agent.name }}</td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-blue-600 hover:underline font-bold text-xs">Edit</button>
                            </td>
                        </tr>
                        <tr v-if="agents.length === 0">
                            <td colspan="3" class="px-6 py-10 text-center text-gray-400 italic">
                                Belum ada data agent. Klik tombol "Tambah Agent" untuk mengisi.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold mb-4">Tambah Agent</h2>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Kode Agent</label>
                        <input v-model="form.code" type="text" class="w-full border-gray-300 rounded-md shadow-sm" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Nama Agent</label>
                        <input v-model="form.name" type="text" class="w-full border-gray-300 rounded-md shadow-sm" required>
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" @click="showModal = false" class="text-gray-500 text-sm font-bold px-4">Batal</button>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded font-bold text-sm">Simpan</button>
                    </div>
                </form>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>