<script setup>
import { ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import { Head, useForm, router } from "@inertiajs/vue3";
import { useToast } from "vue-toastification";

const props = defineProps({
    roles: Array,
    permissions: Array, // Pastikan dikirim dari Controller
});

const showModal = ref(false);
const isEditing = ref(false);
const editId = ref(null);
const toast = useToast();
const showDeleteModal = ref(false);
const roleToDelete = ref(null);

const form = useForm({
    name: "",
    permissions: [], // Array untuk menyimpan ID permission yang dipilih
});

const openAddModal = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (role) => {
    form.clearErrors();
    isEditing.value = true;
    editId.value = role.id;
    form.name = role.name;
    // Map permissions yang dimiliki role ke dalam array form
    form.permissions = role.permissions.map((p) => p.name);
    showModal.value = true;
};

const submit = () => {
    const options = {
        onSuccess: () => {
            toast.success(isEditing.value ? "Role berhasil diperbarui!" : "Role berhasil ditambahkan!");
            showModal.value = false;
            form.reset();
        },
        onError: () => {
            toast.error("Gagal menyimpan data. Cek kembali inputan Anda.");
        },
    };

    if (isEditing.value) {
        form.put(route("roles.update", editId.value), options);
    } else {
        form.post(route("roles.store"), options);
    }
};

const confirmDelete = (role) => {
    roleToDelete.value = role;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    router.delete(route("roles.destroy", roleToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success("Role berhasil dihapus!");
            showDeleteModal.value = false;
            roleToDelete.value = null;
        },
    });
};
</script>

<template>
    <Head title="Manajemen Roles" />

    <AuthenticatedLayout>
        <template #header>Manajemen Roles</template>

        <div class="space-y-6">
            <div class="flex justify-between items-center bg-white p-4 rounded-lg shadow-sm border">
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Daftar Role & Hak Akses</h3>
                    <p class="text-xs text-gray-500">Total: {{ roles.length }} role terdaftar</p>
                </div>
                <button
                    @click="openAddModal"
                    class="bg-indigo-600 text-white px-5 py-2 rounded-md font-bold text-sm hover:bg-indigo-700 transition flex items-center gap-2"
                >
                    <span>+</span> Tambah Role
                </button>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 uppercase font-bold text-[10px] border-b">
                        <tr>
                            <th class="px-6 py-4 text-left w-1/4">Nama Role</th>
                            <th class="px-6 py-4 text-left">Permissions</th>
                            <th class="px-6 py-4 text-center w-48">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="role in roles" :key="role.id" class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 font-bold text-indigo-600 uppercase tracking-tight">
                                {{ role.name }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    <span 
                                        v-for="perm in role.permissions" :key="perm.id"
                                        class="px-2 py-0.5 bg-gray-100 text-gray-600 text-[10px] rounded border border-gray-200"
                                    >
                                        {{ perm.name }}
                                    </span>
                                    <span v-if="role.permissions.length === 0" class="text-gray-400 italic text-xs">
                                        Tidak ada permission
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-center items-center gap-2">
                                    <button
                                        @click="openEditModal(role)"
                                        class="inline-flex items-center px-3 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-md font-bold text-[11px] hover:bg-blue-600 hover:text-white transition-all"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        @click="confirmDelete(role)"
                                        class="inline-flex items-center px-3 py-1 bg-red-50 text-red-700 border border-red-200 rounded-md font-bold text-[11px] hover:bg-red-600 hover:text-white transition-all"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold mb-4 border-b pb-2">
                    {{ isEditing ? "Update Role" : "Tambah Role Baru" }}
                </h2>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Nama Role</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="Contoh: admin, manager, staff"
                            required
                        />
                        <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-3 uppercase">Pilih Hak Akses (Permissions)</label>
                        <div class="grid grid-cols-2 gap-3 max-h-60 overflow-y-auto p-3 border rounded-md bg-gray-50">
                            <div v-for="permission in permissions" :key="permission.id" class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    :id="'perm-' + permission.id"
                                    :value="permission.name"
                                    v-model="form.permissions"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm"
                                />
                                <label :for="'perm-' + permission.id" class="text-sm text-gray-700 select-none cursor-pointer">
                                    {{ permission.name }}
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 mt-6">
                        <button type="button" @click="showModal = false" class="text-gray-500 text-sm font-bold px-4">
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-indigo-600 text-white px-6 py-2 rounded font-bold text-sm disabled:opacity-50"
                        >
                            {{ isEditing ? "Simpan Perubahan" : "Buat Role" }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-800 border-b pb-3">Konfirmasi Hapus Role</h2>
                <div class="mt-4">
                    <p class="text-sm text-gray-600">Yakin ingin menghapus role:</p>
                    <p class="text-md font-bold text-red-600 mt-1" v-if="roleToDelete">
                        {{ roleToDelete.name.toUpperCase() }}
                    </p>
                    <p class="mt-4 text-[11px] text-gray-500 italic">
                        *Menghapus role dapat menyebabkan pengguna dengan role ini kehilangan akses sistem.
                    </p>
                </div>
                <div class="flex justify-end gap-3 mt-8">
                    <button @click="showDeleteModal = false" class="text-gray-500 text-sm font-bold px-4 py-2 hover:bg-gray-100 rounded-md transition">
                        Batal
                    </button>
                    <button @click="executeDelete" class="bg-red-600 text-white px-5 py-2 rounded-md font-bold text-sm hover:bg-red-700 shadow-sm transition">
                        Ya, Hapus Role
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>