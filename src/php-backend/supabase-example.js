// Contoh penggunaan Supabase dalam aplikasi
import supabase from './supabase.js'

// Contoh fungsi untuk mendapatkan data absensi dari Supabase
async function getAttendanceData() {
  try {
    // Mengambil data dari tabel 'public_attendance' di Supabase
    const { data, error } = await supabase
      .from('public_attendance')
      .select('*')
    
    if (error) {
      console.error('Error mengambil data absensi:', error)
      return null
    }
    
    console.log('Data absensi berhasil diambil:', data)
    return data
  } catch (err) {
    console.error('Terjadi kesalahan:', err)
    return null
  }
}

// Contoh fungsi untuk menyimpan data absensi ke Supabase
async function saveAttendance(attendanceData) {
  try {
    // Menyimpan data ke tabel 'public_attendance' di Supabase
    const { data, error } = await supabase
      .from('public_attendance')
      .insert([attendanceData])
    
    if (error) {
      console.error('Error menyimpan data absensi:', error)
      return false
    }
    
    console.log('Data absensi berhasil disimpan:', data)
    return true
  } catch (err) {
    console.error('Terjadi kesalahan:', err)
    return false
  }
}

// Contoh penggunaan fungsi
// getAttendanceData()
//   .then(data => console.log('Data yang diambil:', data))
//   .catch(err => console.error('Error:', err))

// Contoh data absensi
// const newAttendance = {
//   full_name: 'Nama Lengkap',
//   nim: '12345678',
//   prodi: 'Teknik Informatika',
//   date: new Date().toISOString().split('T')[0],
//   check_in_time: new Date().toTimeString().split(' ')[0],
//   latitude_in: -6.914744,
//   longitude_in: 107.609810
// }

// saveAttendance(newAttendance)
//   .then(success => console.log('Berhasil simpan data:', success))
//   .catch(err => console.error('Error:', err))

export { getAttendanceData, saveAttendance }