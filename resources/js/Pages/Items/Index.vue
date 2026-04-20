<script setup>
import { ref, computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import DangerButton from "@/Components/DangerButton.vue";

import { Head, useForm, router } from "@inertiajs/vue3";
import { useToast } from "vue-toastification";

const props = defineProps({
    items: Object,
    groups: Array,
});

const toast = useToast();

const showModal = ref(false);
const showDeleteModal = ref(false);
const showImportModal = ref(false);

const isEditing = ref(false);
const editId = ref(null);
const itemToDelete = ref(null);

/* ✅ FIX: support array & pagination */
const itemList = computed(() => props.items?.data || props.items || []);

const importForm = useForm({ file: null });

const form = useForm({
    item_code: "",
    item_name: "",
    item_per_box: "",
    item_group: "",
});

const submitImport = () => {
    importForm.post(route("items.import"), {
        forceFormData: true,
        onSuccess: () => {
            toast.success("Import berhasil!");
            showImportModal.value = false;
            importForm.reset();
        },
        onError: () => toast.error("Gagal import file."),
    });
};

const openAddModal = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (item) => {
    isEditing.value = true;
    editId.value = item.id;
    Object.assign(form, item);
    showModal.value = true;
};

const submit = () => {
    const options = {
        onSuccess: () => {
            toast.success(
                isEditing.value ? "Item diupdate!" : "Item ditambah!",
            );
            showModal.value = false;
            form.reset();
        },
        onError: () => toast.error("Error input."),
    };

    isEditing.value
        ? form.put(route("items.update", editId.value), options)
        : form.post(route("items.store"), options);
};

const confirmDelete = (item) => {
    itemToDelete.value = item;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    router.delete(route("items.destroy", itemToDelete.value.id), {
        onSuccess: () => {
            toast.success("Item dihapus!");
            showDeleteModal.value = false;
        },
    });
};

/* ✅ pagination */
const goToPage = (url) => {
    if (!url) return;
    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Head title="Master Items" />

    <AuthenticatedLayout>
        <template #header>Master Items</template>

        <div class="space-y-6 p-4">
            <!-- HEADER -->
            <div
                class="flex justify-between items-center bg-white p-4 rounded-lg shadow-sm border"
            >
                <div>
                    <h3 class="text-lg font-bold text-gray-800">
                        Master Data Item
                    </h3>
                    <p class="text-xs text-gray-500">
                        Total: {{ items.total || itemList.length }} item
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="showImportModal = true"
                        class="bg-green-600 text-white px-4 py-2 rounded-md text-sm font-bold hover:bg-green-700"
                    >
                        Import Excel
                    </button>

                    <button
                        @click="openAddModal"
                        class="bg-indigo-600 text-white px-5 py-2 rounded-md text-sm font-bold hover:bg-indigo-700"
                    >
                        + Tambah Item
                    </button>
                </div>
            </div>

            <!-- TABLE -->
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <table class="w-full text-sm">
                    <thead
                        class="bg-gray-50 text-gray-600 text-xs uppercase border-b"
                    >
                        <tr>
                            <th class="px-6 py-4 text-left">Item Code</th>
                            <th class="px-6 py-4 text-left">Item Name</th>
                            <th class="px-6 py-4 text-center">Box</th>
                            <th class="px-6 py-4 text-center">Group</th>
                            <th class="px-6 py-4 text-center">Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        <tr v-for="item in itemList" :key="item.id">
                            <td
                                class="px-6 py-4 font-mono text-indigo-600 font-bold"
                            >
                                {{ item.item_code }}
                            </td>

                            <td class="px-6 py-4">{{ item.item_name }}</td>

                            <td class="px-6 py-4 text-center">
                                {{ item.item_per_box }}
                            </td>

                            <td class="px-6 py-4 text-center">
                                {{ item.item_group || "-" }}
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex justify-center gap-2">
                                    <button
                                        @click="openEditModal(item)"
                                        class="bg-blue-50 text-blue-700 px-3 py-1 rounded text-xs font-bold hover:bg-blue-600 hover:text-white"
                                    >
                                        Edit
                                    </button>

                                    <button
                                        @click="confirmDelete(item)"
                                        class="bg-red-50 text-red-700 px-3 py-1 rounded text-xs font-bold hover:bg-red-600 hover:text-white"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="itemList.length === 0">
                            <td
                                colspan="5"
                                class="py-10 text-center text-gray-400 italic"
                            >
                                Belum ada data
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- PAGINATION -->
                <div
                    v-if="items.links"
                    class="flex justify-between items-center p-4 border-t"
                >
                    <div class="text-xs text-gray-500">
                        Showing {{ items.from }} - {{ items.to }} of
                        {{ items.total }}
                    </div>

                    <div class="flex gap-1">
                        <template v-for="(link, i) in items.links" :key="i">
                            <button
                                v-if="link.url"
                                @click="goToPage(link.url)"
                                v-html="link.label"
                                class="px-3 py-1 text-xs rounded border"
                                :class="
                                    link.active
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-white hover:bg-gray-100'
                                "
                            />

                            <span
                                v-else
                                v-html="link.label"
                                class="px-3 py-1 text-xs text-gray-400"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- IMPORT MODAL -->
        <Modal :show="showImportModal" @close="showImportModal = false">
            <div class="p-6">
                <h2 class="font-bold mb-4">Import Excel</h2>

                <input
                    type="file"
                    @change="(e) => (importForm.file = e.target.files[0])"
                    class="mb-4"
                />

                <div class="flex justify-end gap-2">
                    <SecondaryButton @click="showImportModal = false">
                        Batal
                    </SecondaryButton>

                    <PrimaryButton @click="submitImport">
                        Upload
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- DELETE MODAL -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6 text-center">
                <h2 class="font-bold">Hapus Item?</h2>
                <p class="text-sm mt-2">{{ itemToDelete?.item_name }}</p>

                <div class="flex justify-center gap-2 mt-4">
                    <SecondaryButton @click="showDeleteModal = false"
                        >Batal</SecondaryButton
                    >
                    <DangerButton @click="executeDelete">Hapus</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
