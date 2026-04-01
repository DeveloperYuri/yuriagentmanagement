<script setup>
import { ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import { Head, useForm, router } from "@inertiajs/vue3";
import { useToast } from "vue-toastification";

defineProps({ agents: Array });

const showModal = ref(false);
const isEditing = ref(false); // Penanda apakah sedang edit atau tambah
const editId = ref(null); // Menyimpan ID yang sedang diedit
const toast = useToast();
const fileInput = ref(null);
const showDeleteModal = ref(false);
const agentToDelete = ref(null);

const form = useForm({
    code: "",
    name: "",
});

// Fungsi untuk buka modal TAMBAH
const openAddModal = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset(); // Mengosongkan input code dan name
    form.clearErrors(); // Menghapus pesan error merah jika ada
    showModal.value = true;
};

// Fungsi untuk buka modal EDIT
const openEditModal = (agent) => {
    form.clearErrors();
    isEditing.value = true;
    editId.value = agent.id;
    form.code = agent.code;
    form.name = agent.name;
    showModal.value = true;
};

const submit = () => {
    const options = {
        onSuccess: () => {
            // 1. Notifikasi Sukses
            const message = isEditing.value
                ? "Data Agent berhasil diperbarui!"
                : "Agent baru berhasil ditambahkan!";

            toast.success(message);

            // 2. Reset & Tutup Modal
            // Jika kamu punya fungsi closeModal(), sebaiknya panggil itu saja
            showModal.value = false;
            form.reset();
            form.clearErrors();
        },
        onError: () => {
            // Notifikasi jika ada error validasi atau server
            toast.error("Terjadi kesalahan. Silakan cek kembali inputan Anda.");
        },
        onFinish: () => {
            // Opsional: stop loading jika ada
        },
    };

    if (isEditing.value) {
        form.put(route("agents.update", editId.value), options);
    } else {
        form.post(route("agents.store"), options);
    }
};

const confirmDelete = (agent) => {
    agentToDelete.value = agent;
    showDeleteModal.value = true;
};

// Fungsi eksekusi hapus
const executeDelete = () => {
    router.delete(route("agents.destroy", agentToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success("Agent berhasil dihapus!");
            showDeleteModal.value = false;
            agentToDelete.value = null;
        },
        onError: () => {
            toast.error("Gagal menghapus agent. Mungkin data masih digunakan?");
        },
    });
};

// const deleteAgent = (agent) => {
//     if (confirm(`Apakah Anda yakin ingin menghapus agent ${agent.name}?`)) {
//         router.delete(route("agents.destroy", agent.id), {
//             preserveScroll: true,
//         });
//     }
// };
</script>

<template>
    <Head title="Daftar Agents" />

    <AuthenticatedLayout>
        <template #header>Daftar Agents</template>

        <div class="space-y-6">
            <div
                class="flex justify-between items-center bg-white p-4 rounded-lg shadow-sm border"
            >
                <div>
                    <h3 class="text-lg font-bold text-gray-800">
                        Data Master Agent
                    </h3>
                    <p class="text-xs text-gray-500">
                        Total: {{ agents.length }} agent
                    </p>
                </div>
                <button
                    @click="openAddModal"
                    class="bg-indigo-600 text-white px-5 py-2 rounded-md font-bold text-sm hover:bg-indigo-700 transition flex items-center gap-2"
                >
                    <span>+</span> Tambah Agent
                </button>
            </div>

            <div
                class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden"
            >
                <table class="w-full text-sm">
                    <thead
                        class="bg-gray-50 text-gray-600 uppercase font-bold text-[10px] border-b"
                    >
                        <tr>
                            <th class="px-6 py-4 text-center">Kode</th>
                            <th class="px-6 py-4 text-center">Nama Agent</th>
                            <th class="px-6 py-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="agent in agents"
                            :key="agent.id"
                            class="hover:bg-gray-50 transition"
                        >
                            <td
                                class="px-6 py-4 font-mono text-indigo-600 font-bold text-center"
                            >
                                {{ agent.code }}
                            </td>

                            <td class="px-6 py-4 text-gray-800 text-center">
                                {{ agent.name }}
                            </td>

                            <td class="px-6 py-4">
                                <div
                                    class="flex justify-center items-center gap-2"
                                >
                                    <button
                                        @click="openEditModal(agent)"
                                        class="inline-flex items-center px-3 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-md font-bold text-[11px] hover:bg-blue-600 hover:text-white transition-colors duration-200"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-3 w-3 mr-1"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                            />
                                        </svg>
                                        Edit
                                    </button>

                                    <button
                                        @click="confirmDelete(agent)"
                                        class="inline-flex items-center px-3 py-1 bg-red-50 text-red-700 border border-red-200 rounded-md font-bold text-[11px] hover:bg-red-600 hover:text-white transition-colors duration-200"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-3 w-3 mr-1"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                            />
                                        </svg>
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="agents.length === 0">
                            <td
                                colspan="3"
                                class="px-6 py-10 text-center text-gray-400 italic"
                            >
                                Belum ada data agent. Klik tombol "Tambah Agent"
                                untuk mengisi.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="showModal" :closeable="false" @close="showModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold mb-4">
                    {{ isEditing ? "Edit Agent" : "Tambah Agent" }}
                </h2>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label
                            class="block text-xs font-bold text-gray-500 mb-1 uppercase"
                            >Kode Agent</label
                        >
                        <input
                            v-model="form.code"
                            type="text"
                            class="w-full border-gray-300 rounded-md shadow-sm"
                            required
                        />
                        <div
                            v-if="form.errors.code"
                            class="text-red-500 text-xs mt-1"
                        >
                            {{ form.errors.code }}
                        </div>
                    </div>
                    <div>
                        <label
                            class="block text-xs font-bold text-gray-500 mb-1 uppercase"
                            >Nama Agent</label
                        >
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full border-gray-300 rounded-md shadow-sm"
                            required
                        />
                    </div>

                    <div class="flex justify-end gap-2 mt-4">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="text-gray-500 text-sm font-bold px-4"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-indigo-600 text-white px-4 py-2 rounded font-bold text-sm disabled:opacity-50"
                        >
                            {{ isEditing ? "Update Data" : "Simpan" }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal
            :show="showDeleteModal"
            :closeable="false"
            @close="showDeleteModal = false"
        >
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-800 border-b pb-3">
                    Konfirmasi Hapus
                </h2>

                <div class="mt-4">
                    <p class="text-sm text-gray-600">
                        Apakah Anda yakin ingin menghapus agent:
                    </p>
                    <p
                        class="text-md font-bold text-indigo-600 mt-1"
                        v-if="agentToDelete"
                    >
                        {{ agentToDelete.code }} - {{ agentToDelete.name }}
                    </p>
                    <p class="mt-3 text-[11px] text-red-500 italic">
                        *Tindakan ini tidak dapat dibatalkan dan mungkin
                        berpengaruh pada data laporan terkait.
                    </p>
                </div>

                <div class="flex justify-end gap-3 mt-8">
                    <button
                        type="button"
                        @click="showDeleteModal = false"
                        class="text-gray-500 text-sm font-bold px-4 py-2 hover:bg-gray-100 rounded-md transition"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="executeDelete"
                        class="bg-red-600 text-white px-5 py-2 rounded-md font-bold text-sm hover:bg-red-700 shadow-sm transition"
                    >
                        Ya, Hapus Agent
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
