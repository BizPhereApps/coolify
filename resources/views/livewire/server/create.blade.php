<div class="w-full">
    <div class="flex flex-col gap-4">
        @can('viewAny', App\Models\CloudProviderToken::class)
            <div>
                <x-modal-input title="Connect a Hetzner Server">
                    <x-slot:content>
                        <div class="relative gap-2 cursor-pointer coolbox group">
                            <div class="flex items-center gap-4 mx-6">
                                <svg class="w-10 h-10 flex-shrink-0" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="200" height="200" fill="#D50C2D" rx="8" />
                                    <path d="M40 40 H60 V90 H140 V40 H160 V160 H140 V110 H60 V160 H40 Z" fill="white" />
                                </svg>
                                <div class="flex flex-col justify-center flex-1">
                                    <div class="box-title">Connect a Hetzner Server</div>
                                    <div class="box-description">
                                        Deploy servers directly from your Hetzner Cloud account
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-slot:content>
                    <livewire:server.new.by-hetzner :private_keys="$private_keys" :limit_reached="$limit_reached" />
                </x-modal-input>
            </div>

            <div>
                <x-modal-input title="Connect a DigitalOcean Droplet">
                    <x-slot:content>
                        <div class="relative gap-2 cursor-pointer coolbox group">
                            <div class="flex items-center gap-4 mx-6">
                                <svg class="w-10 h-10 flex-shrink-0" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="32" height="32" fill="#0080FF" rx="6" />
                                    <path fill="#fff" d="M16 6a10 10 0 0 0-10 10h4a6 6 0 0 1 6-6v-4zM10 16h6v6a6 6 0 0 1-6-6zm6 10v4a14 14 0 0 0 14-14H16v10z" />
                                </svg>
                                <div class="flex flex-col justify-center flex-1">
                                    <div class="box-title">Connect a DigitalOcean Droplet</div>
                                    <div class="box-description">
                                        Deploy droplets directly from your DigitalOcean account
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-slot:content>
                    <livewire:server.new.by-digital-ocean />
                </x-modal-input>
            </div>

            <div class="border-t dark:border-coolgray-300 my-4"></div>
        @endcan

        <div>
            <h3 class="pb-2">Add Server by IP Address</h3>
            <livewire:server.new.by-ip :private_keys="$private_keys" :limit_reached="$limit_reached" />
        </div>
    </div>
</div>
