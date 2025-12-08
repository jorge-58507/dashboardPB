<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Crear Usuario') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Asigne el nombre, correo electrónico, contraseña y rol del nuevo usuario.') }}
        </p>
    </header>

    <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-6">
        @csrf

        <div>
            <x-input-label for="name" :value="__('Nombre')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="email" :value="__('Correo Electrónico')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Contraseña')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Repetir Contraseña')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="role_id" :value="__('Rol de Usuario')" />
            
            <select id="role_id" name="role_id" required class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">
                <option value="" disabled selected>{{ __('Seleccione un Rol') }}</option>
                
                {{-- Verifica que la variable $roles esté disponible desde el controlador --}}
                @isset($roles)
                    {{-- Recorre los roles pasados desde el controlador --}}
                    @foreach($roles as $role)
                        {{-- Asegura que el valor enviado es el ID y la etiqueta es el Nombre --}}
                        <option value="{{ $role->id }}" 
                            {{-- Mantiene la selección si hubo un error de validación --}}
                            {{ old('role_id') == $role->id ? 'selected' : '' }}
                        >
                            {{ $role->name }}
                        </option>
                    @endforeach
                @endisset
                
                {{-- Manejo de error si $roles no está definido (opcional) --}}
                @empty($roles)
                    <option disabled>{{ __('No hay roles disponibles.') }}</option>
                @endempty
            </select>
            
            {{-- Muestra errores de validación para el campo role_id --}}
            <x-input-error :messages="$errors->get('role_id')" class="mt-2" />
        </div>
        {{-- ------------------------------------------------ --}}

        <div class="flex items-center justify-end mt-4">
            <x-primary-button class="ml-4">
                {{ __('Crear Usuario') }}
            </x-primary-button>
        </div>
    </form>
</section>