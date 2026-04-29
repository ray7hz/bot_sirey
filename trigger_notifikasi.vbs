' =====================================================
' VBScript untuk Trigger Notifikasi Bot SiRey
' File: trigger_notifikasi.vbs
' Lokasi: d:\APLIKASI\xampp\htdocs\bot_sirey\
' =====================================================

Option Explicit

Dim objShell, objFSO, strBotPath, strPHPPath, strXamppPath
Dim strChoice, strCommand, objExec, strOutput, intResult

' Inisialisasi object
Set objShell = CreateObject("WScript.Shell")
Set objFSO   = CreateObject("Scripting.FileSystemObject")

' Path configuration
strXamppPath = "d:\APLIKASI\xampp"
strBotPath   = "d:\APLIKASI\xampp\htdocs\bot_sirey"
strPHPPath   = strXamppPath & "\php\php.exe"

' Validasi file PHP executable
If Not objFSO.FileExists(strPHPPath) Then
    MsgBox "Error: PHP executable tidak ditemukan di " & strPHPPath, vbCritical, "File Tidak Ditemukan"
    WScript.Quit 1
End If

' Tampilkan menu pilihan
Dim objIE, strHTML
Set objIE = CreateObject("InternetExplorer.Application")

' Buat pilihan dengan InputBox dengan teks deskriptif
strChoice = InputBox("Pilih notifikasi yang akan di-trigger:" & vbCrLf & vbCrLf & _
    "1 = Notifikasi Tugas (deadline besok)" & vbCrLf & _
    "2 = Notifikasi Jadwal (jadwal hari ini)" & vbCrLf & _
    "3 = Trigger Kedua-duanya" & vbCrLf & vbCrLf & _
    "Ketik angka (1/2/3) atau tekan Cancel untuk batal:", _
    "Bot SiRey - Trigger Notifikasi", "1")

' Handle jika user klik Cancel
If strChoice = "" Then
    WScript.Quit 0
End If

' Validasi input
If strChoice <> "1" And strChoice <> "2" And strChoice <> "3" Then
    MsgBox "Pilihan tidak valid. Hanya 1, 2, atau 3 yang diterima.", vbExclamation, "Input Error"
    WScript.Quit 1
End If

' Setup status message
Dim strStatusMsg, strStatusFile, objFile
strStatusFile = strBotPath & "\data\trigger_status.txt"

On Error Resume Next

' Handle pilihan
Select Case strChoice
    Case "1"
        strStatusMsg = "Menjalankan: Notifikasi Tugas" & vbCrLf & vbCrLf
        strCommand = """" & strPHPPath & """ """ & strBotPath & "\telegram\notifikasi_tugas.php"""
        strOutput = RunPHPScript(strCommand)
        strStatusMsg = strStatusMsg & "Output:" & vbCrLf & strOutput
        
    Case "2"
        strStatusMsg = "Menjalankan: Notifikasi Jadwal" & vbCrLf & vbCrLf
        strCommand = """" & strPHPPath & """ """ & strBotPath & "\telegram\notifikasi_jadwal.php"""
        strOutput = RunPHPScript(strCommand)
        strStatusMsg = strStatusMsg & "Output:" & vbCrLf & strOutput
        
    Case "3"
        strStatusMsg = "Menjalankan: KEDUA Notifikasi" & vbCrLf & vbCrLf
        
        strStatusMsg = strStatusMsg & "--- Notifikasi Tugas ---" & vbCrLf
        strCommand = """" & strPHPPath & """ """ & strBotPath & "\telegram\notifikasi_tugas.php"""
        strOutput = RunPHPScript(strCommand)
        strStatusMsg = strStatusMsg & strOutput & vbCrLf & vbCrLf
        
        strStatusMsg = strStatusMsg & "--- Notifikasi Jadwal ---" & vbCrLf
        strCommand = """" & strPHPPath & """ """ & strBotPath & "\telegram\notifikasi_jadwal.php"""
        strOutput = RunPHPScript(strCommand)
        strStatusMsg = strStatusMsg & strOutput
End Select

' Simpan log ke file
Set objFile = objFSO.CreateTextFile(strStatusFile, True)
objFile.WriteLine "[" & Now & "] Notifikasi di-trigger" & vbCrLf & strStatusMsg
objFile.Close()

' Tampilkan hasil
MsgBox strStatusMsg, vbInformation, "Notifikasi Selesai"

' Cleanup
Set objFile = Nothing
Set objFSO = Nothing
Set objShell = Nothing

WScript.Quit 0


' =====================================================
' Function: RunPHPScript
' Menjalankan PHP script dan menangkap output-nya
' =====================================================
Function RunPHPScript(strCmd)
    Dim objWshScriptExec, strResult
    
    On Error Resume Next
    
    Set objWshScriptExec = objShell.Exec(strCmd)
    
    If Err.Number <> 0 Then
        RunPHPScript = "Error: " & Err.Description
        Exit Function
    End If
    
    strResult = objWshScriptExec.StdOut.ReadAll()
    
    If objWshScriptExec.Status <> 0 Then
        strResult = strResult & vbCrLf & "Stderr: " & objWshScriptExec.StdErr.ReadAll()
    End If
    
    If strResult = "" Then
        strResult = "(Tidak ada output)"
    End If
    
    RunPHPScript = strResult
End Function
