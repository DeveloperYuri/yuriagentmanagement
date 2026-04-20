<script setup>
import { ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import DangerButton from "@/Components/DangerButton.vue";

import { Head, useForm, router, Link } from "@inertiajs/vue3";
import { useToast } from "vue-toastification";

const props = defineProps({
    groups: Array,
});

const toast = useToast();

const showModal = ref(false);
const showDeleteModal = ref(false);
const isEditing = ref(false);
const editId = ref(null);

const deleteTarget = ref(null);

const form = useForm({
    name: "",
});

const openAddModal = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    showModal.value = true;
};

const openEditModal = (group) => {
    isEditing.value = true;
    editId.value = group.id;
    form.name = group.name;
    showModal.value = true;
};

const submit = () => {
    const options = {
        onSuccess: () => {
            toast.success(
                isEditing.value
                    ? "Group berhasil diupdate!"
                    : "Group berhasil ditambahkan!",
            );
            showModal.value = false;
            form.reset();
        },
        onError: () => {
            toast.error("Terjadi kesalahan input.");
        },
    };

    if (isEditing.value) {
        form.put(route("itemsgroups.update", editId.value), options);
    } else {
        form.post(route("itemsgroups.store"), options);
    }
};

const confirmDelete = (group) => {
    deleteTarget.value = group;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    router.delete(route("itemsgroups.destroy", deleteTarget.value.id), {
        onSuccess: () => {
            toast.success("Group berhasil dihapus!");
            showDeleteModal.value = false;
        },
    });
};
</script>

<template>
    <Head title="Items Groups" />

    <AuthenticatedLayout>
        <template #header>Master Item Groups</template>

        <div class="p-4 space-y-6">
            <!-- HEADER -->
            <div
                class="flex justify-between items-center bg-white p-4 border rounded-lg"
            >
                <h2 class="font-bold text-gray-800">Item Groups</h2>

                <button
                    @click="openAddModal"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-bold"
                >
                    + Add Group
                </button>
            </div>

            <!-- TABLE -->
            <div class="bg-white border rounded-lg overflow-hidden">
                <table class="w-full text-sm text-center">
                    <!-- HEADER -->
                    <thead
                        class="bg-gray-50 text-gray-600 uppercase font-bold text-[10px] border-b"
                    >
                        <tr>
                            <th class="px-6 py-4 text-left">Name</th>
                            <th class="px-6 py-4">Action</th>
                        </tr>
                    </thead>

                    <!-- BODY -->
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="group in groups"
                            :key="group.id"
                            class="hover:bg-gray-50 transition"
                        >
                            <!-- NAME -->
                            <td class="px-6 py-4 text-left">
                                <div class="font-bold text-gray-800">
                                    {{ group.name }}
                                </div>
                            </td>

                            <!-- ACTION -->
                            <td class="px-6 py-4">
                                <div class="flex justify-center gap-2">
                                    <!-- EDIT -->
                                    <button
                                        @click="openEditModal(group)"
                                        class="bg-blue-50 text-blue-700 px-3 py-1 rounded-md font-bold text-[11px] hover:bg-blue-600 hover:text-white transition"
                                    >
                                        Edit
                                    </button>

                                    <!-- DELETE -->
                                    <button
                                        @click="confirmDelete(group)"
                                        class="bg-red-50 text-red-700 px-3 py-1 rounded-md font-bold text-[11px] hover:bg-red-600 hover:text-white transition"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- EMPTY STATE -->
                        <tr v-if="groups.length === 0">
                            <td
                                colspan="2"
                                class="px-6 py-10 text-gray-500 italic"
                            >
                                Data tidak ditemukan
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL FORM -->
        <Modal :show="showModal" @close="showModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold mb-4">
                    {{ isEditing ? "Edit Group" : "Tambah Group" }}
                </h2>

                <div>
                    <InputLabel value="Group Name" />
                    <TextInput v-model="form.name" class="w-full mt-1" />
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <SecondaryButton @click="showModal = false">
                        Batal
                    </SecondaryButton>

                    <PrimaryButton @click="submit"> Simpan </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- DELETE MODAL -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6 text-center">
                <h2 class="text-lg font-bold">Hapus Group?</h2>
                <p class="text-sm text-gray-500 mt-2">
                    {{ deleteTarget?.name }}
                </p>

                <div class="flex justify-center gap-3 mt-6">
                    <SecondaryButton @click="showDeleteModal = false">
                        Batal
                    </SecondaryButton>

                    <DangerButton @click="executeDelete"> Hapus </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
