<script setup>
import { ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";

import { Head, useForm } from "@inertiajs/vue3";
import { useToast } from "vue-toastification";

// 🔥 Multiselect
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";

const props = defineProps({
    masters: Array,
    aliases: Array,
});

const toast = useToast();
const showModal = ref(false);

const form = useForm({
    agent_name: "",
    master_name: "",
});

// 🔥 mapping ke options multiselect
const masterOptions = props.masters.map(m => ({
    label: m.item_name,
    value: m.item_name,
}));

// buka modal
const openModal = () => {
    form.reset();
    showModal.value = true;
};

// submit
const submit = () => {
    form.post(route('mapping-produk.store'), {
        onSuccess: () => {
            toast.success("Mapping berhasil disimpan!");
            showModal.value = false;
            form.reset();
        },
        onError: () => {
            toast.error("Gagal menyimpan mapping");
        },
    });
};
</script>

<template>
    <Head title="Mapping Produk" />

    <AuthenticatedLayout>
        <template #header>Mapping Produk</template>

        <div class="p-4 space-y-6">
            <!-- HEADER -->
            <div class="flex justify-between items-center bg-white p-4 border rounded-lg">
                <h2 class="font-bold text-gray-800">Mapping Produk</h2>

                <button
                    @click="openModal"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-bold"
                >
                    + Tambah Mapping
                </button>
            </div>

            <!-- TABLE -->
            <div class="bg-white border rounded-lg overflow-hidden">
                <table class="w-full text-sm text-center">
                    <thead class="bg-gray-50 text-gray-600 uppercase font-bold text-[10px] border-b">
                        <tr>
                            <th class="px-6 py-4 text-left">Agent Name</th>
                            <th class="px-6 py-4 text-left">Master Name</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="a in aliases.data"
                            :key="a.id"
                            class="hover:bg-gray-50 transition"
                        >
                            <td class="px-6 py-4 text-left">
                                <div class="font-bold text-gray-800">
                                    {{ a.agent_name }}
                                </div>
                            </td>

                            <td class="px-6 py-4 text-left">
                                {{ a.master_name }}
                            </td>
                        </tr>

                        <tr v-if="aliases.length === 0">
                            <td colspan="2" class="px-6 py-10 text-gray-500 italic">
                                Data mapping belum ada
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex justify-end mt-4 space-x-2">
                <button
                    v-for="link in aliases.links"
                    :key="link.label"
                    v-html="link.label"
                    @click="$inertia.visit(link.url)"
                    :disabled="!link.url"
                    class="px-3 py-1 border rounded text-sm"
                    :class="{ 'bg-gray-200': link.active }"
                />
            </div>
            </div>
        </div>

        <!-- MODAL -->
        <Modal :show="showModal" @close="showModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold mb-4">
                    Tambah Mapping Produk
                </h2>

                <!-- Nama Agent -->
                <div class="mb-3">
                    <InputLabel value="Nama Produk Agent" />
                    <TextInput v-model="form.agent_name" class="w-full mt-1" />
                </div>

                <!-- 🔥 Multiselect -->
                <div>
                    <InputLabel value="Pilih Master Produk" />

                    <Multiselect
                        v-model="form.master_name"
                        :options="masterOptions"
                        label="label"
                        track-by="value"
                        :reduce="option => option.value"
                        placeholder="Cari produk..."
                        searchable
                        class="mt-1"
                    />
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <SecondaryButton @click="showModal = false">
                        Batal
                    </SecondaryButton>

                    <PrimaryButton @click="submit">
                        Simpan
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>