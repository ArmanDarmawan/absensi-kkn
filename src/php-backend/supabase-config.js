import { createClient } from '@supabase/supabase-js'

// URL Supabase tanpa tanda backtick yang tidak diperlukan
const supabaseUrl = 'https://delsplywmweierlppnnp.supabase.co'

// Pastikan kunci API disimpan dengan aman di file .env
const supabaseKey = process.env.SUPABASE_KEY

// Jika SUPABASE_KEY tidak tersedia, berikan pesan error yang jelas
if (!supabaseKey) {
  console.error('SUPABASE_KEY tidak ditemukan di environment variables. Pastikan file .env sudah dikonfigurasi dengan benar.')
}

// Inisialisasi klien Supabase
const supabase = createClient(supabaseUrl, supabaseKey)

export default supabase