import { createClient } from '@supabase/supabase-js'
import dotenv from 'dotenv'

// Muat variabel lingkungan dari file .env
dotenv.config()

// Ambil URL dan kunci API Supabase dari variabel lingkungan
const supabaseUrl = process.env.SUPABASE_URL
const supabaseKey = process.env.SUPABASE_KEY

// Validasi ketersediaan kredensial
if (!supabaseUrl || !supabaseKey) {
  throw new Error('SUPABASE_URL dan SUPABASE_KEY harus diatur dalam file .env')
}

// Inisialisasi klien Supabase
const supabase = createClient(supabaseUrl, supabaseKey)

export default supabase