<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Confirmation de paiement
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
                <livewire:payment.payment-status-component :order-id="$order->id" />
            </div>
        </div>
    </div>
</x-app-layout>
