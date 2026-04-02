<script setup>
import { ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import DangerButton from "@/Components/DangerButton.vue";
import { Head, useForm, router, Link } from "@inertiajs/vue3";
import { useToast } from "vue-toastification";

const props = defineProps({
    users: Object,
    roles: Array,
    regionals: Array,
});

const showModal = ref(false);
const isEditing = ref(false);
const editId = ref(null);
const toast = useToast();
const showDeleteModal = ref(false);
const userToDelete = ref(null);

const form = useForm({
    name: "",
    email: "",
    password: "",
    role: "",
    regional_id: "",
});

const openAddModal = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (user) => {
    form.clearErrors();
    isEditing.value = true;
    editId.value = user.id;
    form.name = user.name;
    form.email = user.email;
    form.password = "";
    form.role = user.roles && user.roles.length > 0 ? user.roles[0].name : "";
    form.regional_id = user.regional_id || "";
    showModal.value = true;
};

const submit = () => {
    const options = {
        onSuccess: () => {
            toast.success(
                isEditing.value
                    ? "Data User diperbarui!"
                    : "User berhasil ditambah!",
            );
            showModal.value = false;
            form.reset();
        },
        onError: () =>
            toast.error("Terjadi kesalahan. Cek kembali inputan Anda."),
    };

    if (isEditing.value) {
        form.put(route("users.update", editId.value), options);
    } else {
        form.post(route("users.store"), options);
    }
};

const confirmDelete = (user) => {
    userToDelete.value = user;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    router.delete(route("users.destroy", userToDelete.value.id), {
        onSuccess: () => {
            toast.success("User berhasil dihapus!");
            showDeleteModal.value = false;
        },
    });
};
</script>

<template>
    <Head title="Manajemen User" />

    <AuthenticatedLayout>
        <template #header>Manajemen User</template>

        <div class="space-y-6 p-4">
            <div
                class="flex justify-between items-center bg-white p-4 rounded-lg shadow-sm border"
            >
                <div>
                    <h3 class="text-lg font-bold text-gray-800">
                        Daftar Pengguna Sistem
                    </h3>
                    <p class="text-xs text-gray-500">
                        Total: {{ users.total || 0 }} user aktif
                    </p>
                </div>
                <button
                    @click="openAddModal"
                    class="bg-blue-600 text-white px-5 py-2 rounded-md font-bold text-sm hover:bg-blue-700 transition flex items-center gap-2"
                >
                    <span>+</span> Tambah User
                </button>
            </div>

            <div
                class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden"
            >
                <table class="w-full text-sm text-center">
                    <thead
                        class="bg-gray-50 text-gray-600 uppercase font-bold text-[10px] border-b"
                    >
                        <tr>
                            <th class="px-6 py-4 text-left">Nama & Email</th>
                            <th class="px-6 py-4">Role</th>
                            <th class="px-6 py-4">Regional</th>
                            <th class="px-6 py-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="hover:bg-gray-50 transition"
                        >
                            <td class="px-6 py-4 text-left">
                                <div class="font-bold text-gray-800">
                                    {{ user.name }}
                                </div>
                                <div class="text-xs text-gray-400 font-mono">
                                    {{ user.email }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-[10px] font-bold uppercase"
                                >
                                    {{
                                        user.roles?.length > 0
                                            ? user.roles[0].name
                                            : "No Role"
                                    }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                {{ user.regional ? user.regional.name : "-" }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-center gap-2">
                                    <button
                                        @click="openEditModal(user)"
                                        class="bg-blue-50 text-blue-700 px-3 py-1 rounded-md font-bold text-[11px] hover:bg-blue-600 hover:text-white transition"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        @click="confirmDelete(user)"
                                        class="bg-red-50 text-red-700 px-3 py-1 rounded-md font-bold text-[11px] hover:bg-red-600 hover:text-white transition"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="users.data.length === 0">
                            <td colspan="4" class="px-6 py-10 text-gray-500">
                                Data tidak ditemukan
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div
                    class="px-6 py-4 bg-gray-50 border-t flex items-center justify-between"
                >
                    <div class="text-xs text-gray-500">
                        Menampilkan {{ users.from || 0 }} sampai
                        {{ users.to || 0 }} dari {{ users.total }} data
                    </div>

                    <div class="flex gap-1">
                        <template v-for="(link, k) in users.links" :key="k">
                            <div
                                v-if="link.url === null"
                                class="px-3 py-1 text-[11px] text-gray-400 border rounded-md"
                                v-html="link.label"
                            />
                            <Link
                                v-else
                                :href="link.url"
                                class="px-3 py-1 text-[11px] border rounded-md transition-colors duration-200"
                                :class="{
                                    'bg-blue-600 text-white border-blue-600':
                                        link.active,
                                    'bg-white text-gray-700 hover:bg-gray-100':
                                        !link.active,
                                }"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <form @submit.prevent="submit" class="p-6">
                <h2 class="text-lg font-bold text-gray-900 border-b pb-3">
                    {{ isEditing ? "Edit Data User" : "Tambah User Baru" }}
                </h2>

                <div class="mt-5 space-y-4">
                    <div>
                        <InputLabel for="name" value="Nama Lengkap" />
                        <TextInput
                            id="name"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.name"
                            required
                        />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                        />
                        <InputError :message="form.errors.email" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel
                            for="password"
                            :value="
                                isEditing
                                    ? 'Password (Kosongkan jika tidak ingin ganti)'
                                    : 'Password'
                            "
                        />
                        <TextInput
                            id="password"
                            type="password"
                            class="mt-1 block w-full"
                            v-model="form.password"
                            :required="!isEditing"
                        />
                        <InputError
                            :message="form.errors.password"
                            class="mt-1"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="role" value="Role Akses" />
                            <select
                                id="role"
                                v-model="form.role"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                required
                            >
                                <option value="">Pilih Role</option>
                                <option
                                    v-for="role in roles"
                                    :key="role.id"
                                    :value="role.name"
                                >
                                    {{ role.name }}
                                </option>
                            </select>
                            <InputError
                                :message="form.errors.role"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel for="regional" value="Regional" />
                            <select
                                id="regional"
                                v-model="form.regional_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                            >
                                <option value="">Pilih Regional</option>
                                <option
                                    v-for="reg in regionals"
                                    :key="reg.id"
                                    :value="reg.id"
                                >
                                    {{ reg.name }}
                                </option>
                            </select>
                            <InputError
                                :message="form.errors.regional_id"
                                class="mt-1"
                            />
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <SecondaryButton @click="showModal = false"
                        >Batal</SecondaryButton
                    >
                    <PrimaryButton
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        {{ isEditing ? "Simpan Perubahan" : "Tambah User" }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6 text-center">
                <div
                    class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4"
                >
                    <span class="text-red-600 font-bold text-xl">!</span>
                </div>
                <h2 class="text-lg font-bold text-gray-900">
                    Konfirmasi Hapus
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Apakah Anda yakin ingin menghapus user
                    <b>{{ userToDelete?.name }}</b
                    >? Data yang sudah dihapus tidak bisa dikembalikan.
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <SecondaryButton @click="showDeleteModal = false"
                        >Batal</SecondaryButton
                    >
                    <DangerButton @click="executeDelete"
                        >Ya, Hapus User</DangerButton
                    >
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
