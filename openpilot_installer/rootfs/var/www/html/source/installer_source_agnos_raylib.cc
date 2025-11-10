#include <time.h>
#include <unistd.h>
#include <cstdlib>
#include <fstream>
#include <map>
#include <string>
#include <thread>
#include <atomic>
#include <mutex>
#include <cstring>
#include <sys/stat.h>
#include <sys/wait.h>
#include <fcntl.h>
#include <iostream>
#include <sstream>
#include <vector>
#include <algorithm>
#include <cmath>

#include <raylib.h>

#define GIT_URL "https://github.com/27182818284590452353602874713526624977572470936999595"  // max 39 + 14 chars for "/openpilot.git"
#define BRANCH "161803398874989484820458683436563811772030917980576286213544862270526046281890244970720720418939113748475408807538689175212663386222353693179318006076672635443338908659593958290563832266131992829026788067520876689250171169620703222104321626954862629631361"  // max 255 chars
#define LOADING_MSG "314159265358979323846264338327950288419"  // max 39 chars
#define GIT_SSH_URL "git@github.com:commaai/openpilot.git"

#define CONTINUE_PATH "/data/continue.sh"

#define CACHE_PATH "/usr/comma/openpilot"
#define INSTALL_PATH "/data/openpilot"
#define TMP_INSTALL_PATH "/data/tmppilot"

// Define BRAND if not already defined
#ifndef BRAND
#define BRAND "openpilot"
#endif

// For testing purposes, we'll create a simple continue script instead of using embedded binary
// In production, these would be linked from the actual embedded continue script
// extern const uint8_t str_continue[] asm("_binary_installer_continue_" BRAND "_sh_start");
// extern const uint8_t str_continue_end[] asm("_binary_installer_continue_" BRAND "_sh_end");

class RaylibInstaller {
private:
    std::atomic<int> progress{0};
    std::atomic<bool> installing{true};
    std::atomic<bool> installation_complete{false};
    std::string title_text = "Installing " LOADING_MSG;
    std::string status_text = "0%";
    std::mutex text_mutex;
    
    // UI constants
    static const int WINDOW_WIDTH = 1920;
    static const int WINDOW_HEIGHT = 1080;
    static const int MARGIN_LEFT = 150;
    static const int MARGIN_TOP = 290;
    static const int PROGRESS_BAR_WIDTH = 1620; // WINDOW_WIDTH - 2 * MARGIN_LEFT
    static const int PROGRESS_BAR_HEIGHT = 72;
    static const int TITLE_FONT_SIZE = 90;
    static const int PERCENT_FONT_SIZE = 70;
    
    // Colors
    Color backgroundColor = BLACK;
    Color textColor = WHITE;
    Color progressBarBg = {41, 41, 41, 255}; // #292929
    
    // Process handling
    int git_pipe[2];
    std::thread install_thread;
    std::thread progress_reader_thread;

public:
    RaylibInstaller() {
        // Initialize pipe for git progress reading
        if (pipe(git_pipe) == -1) {
            perror("pipe");
            exit(1);
        }
    }
    
    ~RaylibInstaller() {
        if (install_thread.joinable()) {
            install_thread.join();
        }
        if (progress_reader_thread.joinable()) {
            progress_reader_thread.join();
        }
        close(git_pipe[0]);
        close(git_pipe[1]);
    }

    void run() {
        InitWindow(WINDOW_WIDTH, WINDOW_HEIGHT, "OpenPilot Installer");
        SetTargetFPS(60);
        
        // Start installation in background thread
        install_thread = std::thread(&RaylibInstaller::doInstall, this);
        
        // Main render loop
        while (!WindowShouldClose() && installing) {
            BeginDrawing();
            ClearBackground(backgroundColor);
            
            drawUI();
            
            EndDrawing();
            
            // Check if installation is complete and wait period has passed
            if (installation_complete && GetTime() > 60.0) {
                installing = false;
            }
        }
        
        CloseWindow();
    }

private:
    void drawUI() {
        std::lock_guard<std::mutex> lock(text_mutex);
        
        // Draw title
        DrawText(title_text.c_str(), MARGIN_LEFT, MARGIN_TOP, TITLE_FONT_SIZE, textColor);
        
        // Draw progress bar
        int progressBarY = MARGIN_TOP + 170 + TITLE_FONT_SIZE;
        drawProgressBar(MARGIN_LEFT, progressBarY);
        
        // Draw percentage
        int percentY = progressBarY + PROGRESS_BAR_HEIGHT + 30;
        DrawText(status_text.c_str(), MARGIN_LEFT, percentY, PERCENT_FONT_SIZE, textColor);
    }
    
    void drawProgressBar(int x, int y) {
        // Background
        DrawRectangle(x, y, PROGRESS_BAR_WIDTH, PROGRESS_BAR_HEIGHT, progressBarBg);
        
        // Progress fill with color interpolation
        int currentProgress = progress.load();
        if (currentProgress > 0) {
            int fillWidth = (PROGRESS_BAR_WIDTH * currentProgress) / 100;
            Color progressColor = getProgressColor(currentProgress);
            DrawRectangle(x, y, fillWidth, PROGRESS_BAR_HEIGHT, progressColor);
        }
    }
    
    Color getProgressColor(int percent) {
        // HSB color interpolation similar to Qt version
        float f = percent / 100.0f;
        int h = (int)(lerp(233, 360 + 131, f)) % 360;
        int s = (int)lerp(78, 62, f);
        int b = (int)lerp(94, 87, f);
        
        // Convert HSB to RGB
        return hsbToRgb(h, s, b);
    }
    
    float lerp(float a, float b, float f) {
        return (a * (1.0f - f)) + (b * f);
    }
    
    Color hsbToRgb(int h, int s, int b) {
        float hf = h / 360.0f;
        float sf = s / 100.0f;
        float bf = b / 100.0f;
        
        float c = bf * sf;
        float x = c * (1 - abs(fmod(hf * 6, 2) - 1));
        float m = bf - c;
        
        float r, g, bl;
        if (hf < 1.0f/6) { r = c; g = x; bl = 0; }
        else if (hf < 2.0f/6) { r = x; g = c; bl = 0; }
        else if (hf < 3.0f/6) { r = 0; g = c; bl = x; }
        else if (hf < 4.0f/6) { r = 0; g = x; bl = c; }
        else if (hf < 5.0f/6) { r = x; g = 0; bl = c; }
        else { r = c; g = 0; bl = x; }
        
        return {
            (unsigned char)((r + m) * 255),
            (unsigned char)((g + m) * 255),
            (unsigned char)((bl + m) * 255),
            255
        };
    }
    
    void updateProgress(int percent) {
        progress = percent;
        std::lock_guard<std::mutex> lock(text_mutex);
        status_text = std::to_string(percent) + "%";
    }
    
    void updateTitle(const std::string& new_title) {
        std::lock_guard<std::mutex> lock(text_mutex);
        title_text = new_title;
    }
    
    bool time_valid() {
        time_t rawtime;
        time(&rawtime);
        
        struct tm * sys_time = gmtime(&rawtime);
        return (1900 + sys_time->tm_year) >= 2020;
    }
    
    void run_command(const char* cmd) {
        int err = std::system(cmd);
        if (err != 0) {
            std::cerr << "Command failed: " << cmd << " (exit code: " << err << ")" << std::endl;
            exit(1);
        }
    }
    
    bool directory_exists(const char* path) {
        struct stat info;
        return stat(path, &info) == 0 && S_ISDIR(info.st_mode);
    }
    
    void doInstall() {
        // wait for valid time
        while (!time_valid()) {
            usleep(500 * 1000);
            std::cout << "Waiting for valid time" << std::endl;
        }
        
        // cleanup
        run_command("rm -rf " TMP_INSTALL_PATH " " INSTALL_PATH);
        
        // do the install
        if (directory_exists(CACHE_PATH)) {
            cachedFetch();
        } else {
            freshClone();
        }
    }
    
    void freshClone() {
        std::cout << "Doing fresh clone" << std::endl;
        
        std::string cmd = "git clone --progress " + std::string(GIT_URL) + " -b " + 
                         std::string(BRANCH) + " --depth=1 --recurse-submodules " + 
                         std::string(TMP_INSTALL_PATH);
        
        executeGitCommand(cmd);
    }
    
    void cachedFetch() {
        std::cout << "Fetching with cache" << std::endl;
        
        run_command("cp -rp " CACHE_PATH " " TMP_INSTALL_PATH);
        
        if (chdir(TMP_INSTALL_PATH) != 0) {
            perror("chdir");
            exit(1);
        }
        
        run_command("git remote set-branches --add origin " BRANCH);
        updateProgress(10);
        
        std::string cmd = "git fetch --progress origin " + std::string(BRANCH);
        executeGitCommand(cmd);
    }
    
    void executeGitCommand(const std::string& cmd) {
        // Start progress reader thread
        progress_reader_thread = std::thread(&RaylibInstaller::readGitProgress, this);
        
        // Execute git command and redirect stderr to our pipe
        std::string full_cmd = cmd + " 2>&1";
        FILE* fp = popen(full_cmd.c_str(), "r");
        if (fp == nullptr) {
            perror("popen");
            exit(1);
        }
        
        char buffer[1024];
        while (fgets(buffer, sizeof(buffer), fp) != nullptr) {
            parseGitProgress(std::string(buffer));
        }
        
        int status = pclose(fp);
        if (status != 0) {
            std::cerr << "Git command failed with status: " << status << std::endl;
            exit(1);
        }
        
        // Wait for progress reader to finish
        if (progress_reader_thread.joinable()) {
            progress_reader_thread.join();
        }
        
        cloneFinished();
    }
    
    void readGitProgress() {
        // This function is kept for compatibility but actual progress parsing
        // is done in parseGitProgress called from executeGitCommand
    }
    
    void parseGitProgress(const std::string& line) {
        // Parse git progress output similar to Qt version
        struct Stage {
            std::string prefix;
            int weight;
        };
        
        std::vector<Stage> stages = {
            {"Receiving objects: ", 95},
            {"Filtering content: ", 5},
        };
        
        int base = 0;
        for (const auto& stage : stages) {
            if (line.find(stage.prefix) == 0) {
                size_t percent_pos = line.find("%");
                if (percent_pos != std::string::npos) {
                    // Find the number before the %
                    size_t start = line.rfind(" ", percent_pos);
                    if (start != std::string::npos) {
                        std::string percent_str = line.substr(start + 1, percent_pos - start - 1);
                        try {
                            float perc = std::stof(percent_str);
                            int p = base + (int)(perc / 100.0f * stage.weight);
                            updateProgress(p);
                        } catch (const std::exception& e) {
                            // Ignore parsing errors
                        }
                    }
                }
                break;
            }
            base += stage.weight;
        }
    }
    
    void cloneFinished() {
        std::cout << "Git operation finished successfully" << std::endl;
        
        // Update UI
        updateTitle("Installation complete");
        updateProgress(100);
        
        // ensure correct branch is checked out
        if (chdir(TMP_INSTALL_PATH) != 0) {
            perror("chdir");
            exit(1);
        }
        
        run_command("git checkout " BRANCH);
        run_command("git reset --hard origin/" BRANCH);
        
        // move into place
        run_command("mv " TMP_INSTALL_PATH " " INSTALL_PATH);
        
#ifdef INTERNAL
        run_command("mkdir -p /data/params/d/");
        
        std::map<std::string, std::string> params = {
            {"SshEnabled", "1"},
            {"RecordFrontLock", "1"},
            {"GithubSshKeys", SSH_KEYS},
        };
        
        for (const auto& [key, value] : params) {
            std::ofstream param;
            param.open("/data/params/d/" + key);
            param << value;
            param.close();
        }
        
        run_command("cd " INSTALL_PATH " && git remote set-url origin --push " GIT_SSH_URL);
#endif
        
        // write continue.sh
        // In production, this would use the embedded binary data
        // For now, create a simple continue script
        std::ofstream continue_script("/data/continue.sh.new");
        if (!continue_script.is_open()) {
            // If we can't write to /data, try current directory for testing
            continue_script.open("./continue.sh.new");
            if (!continue_script.is_open()) {
                std::cerr << "Failed to create continue.sh" << std::endl;
                exit(1);
            }
        }
        
        continue_script << "#!/bin/bash\n";
        continue_script << "# OpenPilot continue script\n";
        continue_script << "# This would normally contain the actual continue logic\n";
        continue_script << "echo \"OpenPilot installation completed successfully\"\n";
        continue_script << "echo \"Restarting services...\"\n";
        continue_script << "# Add actual restart logic here\n";
        continue_script.close();
        
        // Try to set permissions and move the script
        try {
            run_command("chmod +x /data/continue.sh.new");
            run_command("mv /data/continue.sh.new " CONTINUE_PATH);
        } catch (...) {
            // If /data is not accessible (testing environment), use current directory
            run_command("chmod +x ./continue.sh.new");
            std::cout << "Continue script created as ./continue.sh.new (testing mode)" << std::endl;
        }
        
        // Mark installation as complete and start 60-second timer
        installation_complete = true;
        
        // Wait for 60 seconds before allowing exit
        std::this_thread::sleep_for(std::chrono::seconds(60));
        installing = false;
    }
};

int main(int argc __attribute__((unused)), char *argv[] __attribute__((unused))) {
    RaylibInstaller installer;
    installer.run();
    return 0;
}